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

        getURLs as protected getURLsFromXml;
        getAccessNotes as protected getAccessNotesFromXml;
        getNumberingNotes as protected getNumberingNotesFromXml;
        getLanguages as protected getLanguagesFromXml;
        // Method "getFormats" does not exist in MarcAdvancedTrait, but 
        // MarcAdvancedTrait uses MarcBasicTrait where it exists.
        getFormats as protected getFormatsFromXml;
        getBibliographicLevel as protected getBibliographicLevelFromXml;
        getGenres as protected getGenresFromXml;
        // Method "getCallNumbers" does not exist in MarcAdvancedTrait, but 
        // MarcAdvancedTrait uses MarcBasicTrait where it exists.
        getCallNumbers as protected getCallNumbersFromXml;
        getAllSubjects as protected getAllSubjectsFromXml;
        hasChilds as protected hasChildsFromXml;
        hasParents as protected hasParentsFromXml;
        getSeriesVolume as protected getSeriesVolumeFromXml;
        getSummarizedHoldings as protected getSummarizedHoldingsFromXml;
        getPublicationDetailsAut as protected getPublicationDetailsAutFromXml;
        hasItemProvenance as protected hasItemProvenanceFromXml;
        getItemProvenance as protected getItemProvenanceFromXml;
        getRecordBanner as protected getRecordBannerFromXml;
    }

    /**
     * Get field numberingNotes_txt_mv (Numbering Peculiarities Note = Marc 515a)
     *
     * @return array    An array of values in numberingNotes_txt_mv (= Marc 515a)
     */
    public function getNumberingNotes() {
        $numNotes = $this->fields['numberingNotes_txt_mv'] ?? null;
        if ($numNotes == null || empty($numNotes)) {
            $numNotes = $this->getNumberingNotesFromXml();
        }
        return array_unique($numNotes);
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
            $langs = $this->getLanguagesFromXml();
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
            $results = $this->getURLsFromXml();
        }
       
        return $results;
    }

    /**
     * Get access note that describes if a ressource is available with or without
     * restrictions. Fallback to MarcXML if Solr field doesn't exist.
     *
     * @return array    The text of the access note
     */
    public function getAccessNotes() {
        $accessNotes = $this->fields['accessNote_str_mv'] ?? null;
        if (empty($accessNotes)) {
            $accessNotes = $this->getAccessNotesFromXml();
        }
        return $accessNotes;
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
            // AK: Using parent "getTitle" method
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
        if ($nbn = $this->getCleanNBN()) {
            $arr['nbn'] = $nbn['nbn'];
        }
        if ($ismn = $this->getCleanISMN()) {
            $arr['ismn'] = $ismn;
        }

        // If an ILS driver has injected extra details, check for IDs in there
        // to fill gaps:
        if ($ilsDetails = $this->getExtraDetail('ils_details')) {
            foreach (['isbn', 'issn', 'oclc', 'upc', 'nbn', 'ismn'] as $key) {
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

        // Get all formats
        $formats = $this->getFormats();

        // Get the first value of the formats array. If there is no format, use
        // "Unknown".
        $format = (count($formats) > 0) ? $formats[0] : 'Unknown';

        // Return only one format
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
            $formats = $this->getFormatsFromXml();
        }

        // Return the formats
        return $formats;
    }

    /**
     * AK: Overrides the getBibliographicLevel method in MarcAdvancedTrait. Get the
     * level of the record from Solr and then - as a fallback - from MarcXML. If no
     * level was found we return null.
     * 
     * ATTENTION: The value returned here must be one of the values returned from
     * \VuFind\RecordDriver\MarcAdvancedTrait::getBibliographicLevel as it is
     * responsible for e. g. item/title level requests. Values with "Part" in it
     * will always get item level requests.
     *
     * @return string|null   The level of the record as string or null
     */
    public function getBibliographicLevel()
    {
        $level = $this->fields['bibLevel_txtF'] ?? null;
        if ($level == null) {
            $level = $this->getBibliographicLevelFromXml();
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
            $genres = $this->getGenresFromXml();
        }
        return $genres;
    }

    /**
     * AK: Get subject terms from all corresponding Solr fields. As a fallback, get
     * subjects from MarcXML.
     * 
     * TODO: Add Solr fields via a config instead of hard coded!
     *
     * @return array|null   All subject terms as an array or null
     */
    public function getAllSubjects()
    {
        $subjects = array_unique(
            array_merge(
                $this->fields['subjectChainTopic_txt_mv'] ?? [],
                $this->fields['subjectAkTopic0_txt_mv'] ?? [],
                $this->fields['subjectAkGeogr0_txt_mv'] ?? [],
                $this->fields['subjectAkPerson0_txt_mv'] ?? [],
                $this->fields['subjectAkCorporation0_txt_mv'] ?? [],
                $this->fields['subjectAkForm0_txt_mv'] ?? [],
                $this->fields['subjectAkEra0_txtF_mv'] ?? []
            )
        );
        if (empty($subjects)) {
            $subjects = $this->getAllSubjectsFromXml();
        }
        return (empty($subjects)) ? null : $subjects;
    }

    /**
     * AK: Get all content summaries (abstracts) from the corresponding Solr field.
     *
     * @return array|null   All content summaries (abstracts) as an array or null
     */
    public function getContentSummaries()
    {
        return $this->fields['contentSummary_txt_mv'] ?? null;
    }

    /**
     * Get all call numbers from the corresponding Solr field. If nothing was found
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
     * AK: Get names of all contributor
     * 
     * TODO: Fallback to MarcXML
     *
     * @return array
     */
    public function getContributors() {
        // Primary authors
        $primPers = isset($this->fields['author']) ? (array)$this->fields['author'] : [];
        $primCorp = isset($this->fields['author_corporate']) ? (array)$this->fields['author_corporate'] : [];
        $primMeet = isset($this->fields['author_meeting_txt']) ? (array)$this->fields['author_meeting_txt'] : [];
        // Secondary authors
        $secPers = isset($this->fields['author2']) ? (array)$this->fields['author2'] : [];
        $secCorp = isset($this->fields['author2_corporate_txt_mv']) ? (array)$this->fields['author2_corporate_txt_mv'] : [];
        $secMeet = isset($this->fields['author2_meeting_txt_mv']) ? (array)$this->fields['author2_meeting_txt_mv'] : [];
        // Merge array
        $merged = array_merge($primPers, $primCorp, $primMeet, $secPers,$secCorp,
            $secMeet);
            
        // Return merged array
        return $merged;
    }

    /**
     * AK: Get all possible contributors grouped by role in the right order.
     * 
     * TODO: Make that function shorter and cleaner if possible! Implement a fallback
     * to MarcXML!
     *
     * @return array    An array with all contributors grouped by role
     */
    public function getContributorsByRole() {
        // Initialize return variable
        $contributors = [];

        // Initialize other variables
        $name = null;
        $role = null;
        $auth = null;

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

        // Add primary person authors to array (values from Marc21 field 100)
        if ($primaryName) {
            $contributors[$primaryRole][] = [
                'entity' => 'person',
                'name' => $primaryName,
                'role' => $primaryRole,
                'auth' => $primaryAuth,
                'primary' => true
            ];
        }

        // Add primary corporation authors to array (values from Marc21 field 110)
        if ($corpName) {
            $contributors[$corpRole][] = [
                'entity' => 'corporation',
                'name' => $corpName,
                'role' => $corpRole,
                'auth' => $corpAuth,
                'primary' => true
            ];

        }

        // Add primary meeting authors to array (values from Marc21 field 111)
        if ($meetingName) {
            $contributors[$meetingRole][] = [
                'entity' => 'meeting',
                'name' => $meetingName,
                'role' => $meetingRole,
                'auth' => $meetingAuth,
                'primary' => true
            ];
        }
        
        // Add secondary person authors to array (values from Marc21 field 700)
        if ($secNames) {
            foreach ($secNames as $key => $value) {
                if (($key % 3) == 0) { // First of 3 values
    				$name = $value;
    			} else if (($key % 3) == 1) { // Second of 3 values
    				$role = $value;
    			}  else if (($key % 3) == 2) { // Third and last of 3 values
    				$auth = $value;

    				// We have all values now, add them to the return array:
    				$contributors[$role][] = [
                        'entity' => 'person',
                        'name' => $name,
                        'role' => $role,
                        'auth' => $auth,
                        'primary' => false
                    ];
    			}
            }
        }

        // Add secondary corporation authors to array (values from Marc21 field 710)
        if ($secCorps) {
            foreach ($secCorps as $key => $value) {
                if (($key % 3) == 0) { // First of 3 values
    				$name = $value;
    			} else if (($key % 3) == 1) { // Second of 3 values
    				$role = $value;
    			}  else if (($key % 3) == 2) { // Third and last of 3 values
    				$auth = $value;

    				// We have all values now, add them to the return array:
    				$contributors[$role][] = [
                        'entity' => 'corporation',
                        'name' => $name,
                        'role' => $role,
                        'auth' => $auth,
                        'primary' => false
                    ];
    			}
            }
        }

        // Add secondary meeting authors to array (values from Marc21 field 711)
        if ($secMeetings) {
            foreach ($secMeetings as $key => $value) {
                if (($key % 3) == 0) { // First of 3 values
    				$name = $value;
    			} else if (($key % 3) == 1) { // Second of 3 values
    				$role = $value;
    			}  else if (($key % 3) == 2) { // Third and last of 3 values
    				$auth = $value;

    				// We have all values now, add them to the return array:
    				$contributors[$role][] = [
                        'entity' => 'meeting',
                        'name' => $name,
                        'role' => $role,
                        'auth' => $auth,
                        'primary' => false
                    ];
    			}
            }
        }

        return $contributors;
    }

    /**
     * AK: Check if there are parent records. Fallback to MarcXML.
     *
     * @return boolean True if parent records exists, false otherwise
     */
    public function hasParents() {
        $hasParents = isset($this->fields['parents_txt_mv']);
        if (!$hasParents) {
            $hasParents = $this->hasParentsFromXml();
        }
        return $hasParents;
    }

    /**
     * AK: Get information of parent records as an array.
     * TODO: Fallback to MarcXML
     *
     * @return array    An array with information of parent records or null.
     */
    public function getParents() {
        // Define variables
        $result = [];
        $title = null;
        $subTitle = null;
        $partNo = null;
        $partName = null;
        $orderNo = null;
        $form = null;
        $level = null;
        $volNo245 = null;
        $volNo490 = null;
        $volNo773 = null;
        $volNo830 = null;
        $acNo = null;
        $id = null;

        $solr = $this->fields['parents_txt_mv'] ?? null;
        if ($solr) {
            $defaultSolrValues = ['NoRemainder', 'NoPartNo', 'NoPartName',
                'NoOrderNo', 'NoForm', 'NoLevel', 'NoVolNo245', 'NoVolNo490',
                'NoVolNo773', 'NoVolNo830', 'NoAc', 'NoId'];

                foreach ($solr as $key => $value) {
                    if (($key % 13) == 0) { // First of 13 values
                        $title = (in_array($value, $defaultSolrValues)) ? null : $value;
                    } else if (($key % 13) == 1) { // Second of 13 values
                        $subTitle = (in_array($value, $defaultSolrValues))
                            ? null
                            : $value;
                    } else if (($key % 13) == 2) { // Third of 13 values
                        $partNo = (in_array($value, $defaultSolrValues))
                            ? null
                            : $value;
                    } else if (($key % 13) == 3) {
                        $partName = (in_array($value, $defaultSolrValues))
                            ? null
                            : $value;
                    } else if (($key % 13) == 4) {
                        $orderNo = (in_array($value, $defaultSolrValues))
                            ? null
                            : $value;
                    } else if (($key % 13) == 5) {
                        $form = (in_array($value, $defaultSolrValues))
                            ? null
                            : $value;
                    } else if (($key % 13) == 6) {
                        $level = (in_array($value, $defaultSolrValues))
                            ? null
                            : $value;
                    } else if (($key % 13) == 7) {
                        $volNo245 = (in_array($value, $defaultSolrValues))
                            ? null
                            : $value;
                    } else if (($key % 13) == 8) {
                        $volNo490 = (in_array($value, $defaultSolrValues))
                            ? null
                            : $value;
                    } else if (($key % 13) == 9) {
                        $volNo773 = (in_array($value, $defaultSolrValues))
                            ? null
                            : $value;
                    } else if (($key % 13) == 10) {
                        $volNo830 = (in_array($value, $defaultSolrValues)) ? null : $value;
                    } else if (($key % 13) == 11) {
                        $acNo = (in_array($value, $defaultSolrValues)) ? null : $value;
                    } else if (($key % 13) == 12) { // Thirteenth and last of 13 values
                        $id = (in_array($value, $defaultSolrValues)) ? null : $value;

                        // We have all values now, add them to the return array:
                        $result[] = ['title' => $title, 'subTitle' => $subTitle,
                            'partNo' => $partNo, 'partName' => $partName,
                            'orderNo' => $orderNo, 'form' => $form,
                            'level' => $level, 'volNo245' => $volNo245,
                            'volNo490' => $volNo490, 'volNo773' => $volNo773,
                            'volNo830' => $volNo830, 'acNo' => $acNo, 'id' => $id];
                    }
                }
        }

        return empty($result) ? null : $result;
    }

    /**
     * AK: Get parent records in consolidated format
     *
     * @return array|null An array of parent record information or null
     */
    public function getConsolidatedParents() {
        $parentsCons = [];
        $parentsRaw = $this->getParents();
        if ($parentsRaw) {
            foreach ($parentsRaw as $key => $parentRaw) {
                // Consolidate volume numbers into one string
                $volNo = $parentRaw['volNo830'] ?? $parentRaw['volNo773']
                    ?? $parentRaw['volNo490'] ?? $parentRaw['volNo245'];
                
                // Special case "LKR number": If we have a number in volNo773 that
                // starts with "no:", use that one instead.
                if (isset($parentRaw['volNo773']) && strpos($parentRaw['volNo773'], 'no:') === 0) {
                    $volNo = $parentRaw['volNo773'];
                }
                
                // Join possible parts of title to one string
                $title = implode(' : ', array_filter([$parentRaw['title'],
                    $parentRaw['subTitle'], $parentRaw['partNo'],
                    $parentRaw['partName']], array($this, 'filterCallback'))
                );
                // Get the bibl. level
                $level = $parentRaw['level'] ?? null;
                // Add necessary information to return string
                $parentsCons[$key]['id'] = $parentRaw['id'];
                $parentsCons[$key]['title'] = $title;
                $parentsCons[$key]['volNo'] = $volNo;
                $parentsCons[$key]['level'] = $level;
            }
        }
        return empty($parentsCons) ? null : $parentsCons;
    }

    /**
     * AK: Check if there are child records. Fallback to MarcXML.
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
     * AK: Get information of child records as an array.
     * TODO: Fallback to MarcXML
     *
     * @return array    An array with information of child records or null.
     */
    public function getChilds() {
        // Define variables
        $result = [];
        $title = null;
        $subTitle = null;
        $partTitle = null;
        $edition = null;
        $pubYear = null;
        $relatedPart = null;
        $enumeration = null;
        $form = null;
        $level = null;
        $volNo = null;
        $issNo = null;
        $pgNos = null;
        $orderNo = null;
        $depth = null;
        $fullTextUrl = null;
        $marker = null;
        $acNo = null;
        $id = null;

        $solr = $this->fields['childs_txt_mv'] ?? null;
        if ($solr) {
            $defaultSolrValues = ['NoRemainder', 'NoPartName', 'NoEdt', 'NoYear',
                'NoRelPart', 'NoEnumNo', 'NoForm', 'NoLevel', 'NoVolNo', 'NoIssNo',
                'NoPg', 'NoOrderNo', 'NoDepth', 'NoUrl', 'NoMarker', 'NoAc', 'NoId'];

            foreach ($solr as $key => $value) {
    			if (($key % 18) == 0) { // First of 18 values
    				$title = (in_array($value, $defaultSolrValues)) ? null : $value;
    			} else if (($key % 18) == 1) { // Second of 18 values
    				$subTitle = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
    			} else if (($key % 18) == 2) { // Third of 18 values
                    $partTitle = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 18) == 3) {
                    $edition = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 18) == 4) {
                    $pubYear = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 18) == 5) {
                    $relatedPart = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 18) == 6) {
                    $enumeration = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 18) == 7) {
                    $form = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 18) == 8) {
                    $level = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 18) == 9) {
                    $volNo = (in_array($value, $defaultSolrValues)) ? null : $value;
                } else if (($key % 18) == 10) {
                    $issNo = (in_array($value, $defaultSolrValues)) ? null : $value;
                } else if (($key % 18) == 11) {
                    $pgNos = (in_array($value, $defaultSolrValues)) ? null : $value;
                } else if (($key % 18) == 12) {
                    $orderNo = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 18) == 13) {
                    $depth = (in_array($value, $defaultSolrValues)) ? null : $value;
                } else if (($key % 18) == 14) {
                    $fullTextUrl = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 18) == 15) {
                    $marker = (in_array($value, $defaultSolrValues)) ? null : $value;
                } else if (($key % 18) == 16) {
                    $acNo = (in_array($value, $defaultSolrValues)) ? null : $value;
                } else if (($key % 18) == 17) { // Eighteenth and last of 18 values
                    $id = (in_array($value, $defaultSolrValues)) ? null : $value;

                    // We have all values now, add them to the return array:
                    $result[] = ['title' => $title, 'subTitle' => $subTitle,
                        'partTitle' => $partTitle, 'edition' => $edition,
                        'pubYear' => $pubYear, 'relatedPart' => $relatedPart,
                        'enumeration' => $enumeration, 'form' => $form,
                        'level' => $level, 'volNo' => $volNo, 'issNo' => $issNo,
                        'pgNos' => $pgNos, 'orderNo' => $orderNo, 'depth' => $depth,
                        'fullTextUrl' => $fullTextUrl, 'marker' => $marker,
                        'acNo' => $acNo, 'id' => $id];
                }
    		}
        }

        return empty($result) ? null : $result;
    }

    /**
     * AK: Get a volume number if available. Returns volume numbers of series items
     *     or multivolume items. For volume numbers of journal articles or chapters,
     *     see getContainerVolume().
     *
     * @return string|null   The volume number of a monographic item or null
     */
    public function getSeriesVolume() {
        $volNo = null;
        // Check if we have a "part" record. If not, the current record could be
        // a volume from a series or multivolume work.
        if (strpos(strtolower($this->getBibliographicLevel()), 'part') === false) {
            $volNos = $this->fields['volumeNo_str_mv'] ?? null;
            $volNo = !empty($volNos) ? $volNos[0] : null;
        }
        // Fallback to MarcXML
        if (!$volNo) {
            $volNo = $this->getSeriesVolumeFromXml();
        }
        return $volNo;        
    }

    /**
     * AK: Get publication date(s) if there is not date-span available.
     *
     * @return array    The publication date(s)
     */
    public function getPublicationDatesWithoutDateSpan() {
        return(isset($this->fields['publishDate'])
            && !isset($this->fields['dateSpan']))
            ? $this->fields['publishDate']
            : [];
    }

    /**
     * Get publication details from 264 fields. This function take several variants
     * of subfield notation into account, like e. g. multiple subfields a and b.
     * 
     * For these special cases in austrian libraries, see:
     * https://wiki.obvsg.at/Katalogisierungshandbuch/Kategorienuebersicht264FE
     * 
     * We don't (yet) use data from the Solr index. We get them from MarcXML.
     *
     * @return array
     */
    public function getPublicationDetailsAut() {
        // Get publication details from MarcXML. We don't (yet) use data from the
        // Solr index.
        return $this->getPublicationDetailsAutFromXml();
    }

    /**
     * AK: Get publisher place and name in form "Place : Publisher Name". If only
     * one of both values exists, the single value will be returned.
     *
     * @return String   The place and/or name of the publisher or null
     */
    public function getPulisherPlaceName() {
        $retval = null;

        $publishers = null;
		if (!empty($arrPublishers = $this->getPublishers())) {
			$publishers = join(', ', $arrPublishers);
        }

        $places = null;
		if (!empty($arrPlaces = $this->getPublicationInfo())) {
			$places = join(', ', $arrPlaces);
        }

        $retval = implode(' : ', array_filter(
            [$places, $publishers],
            array($this, 'filterCallback'))
        );
        
        return $retval;
    }

    /**
     * AK: Get alternative title(s).
     * TODO: Fallback to MarcXML
     * 
     * @return array An array or null
     */
    public function getTitleAlt() {
        return $this->fields['title_alt'] ?? null;
    }

    /**
     * AK: Get form(s).
     * TODO: Fallback to MarcXML
     * 
     * @return array An array or null
     */
    public function getForms() {
        return $this->fields['bibForm_txtF_mv'] ?? null;
    }

    /**
     * AK: Get content(s).
     * TODO: Fallback to MarcXML
     * 
     * @return array An array or null
     */
    public function getContents() {
        return $this->fields['bibContent_txtF_mv'] ?? null;
    }

    /**
     * AK: Get media types(s).
     * TODO: Fallback to MarcXML
     * 
     * @return array An array or null
     */
    public function getMediaTypes() {
        return $this->fields['bibMedia_txtF_mv'] ?? null;
    }

    /**
     * AK: Get media carrier(s).
     * TODO: Fallback to MarcXML
     * 
     * @return array An array or null
     */
    public function getCarriers() {
        return $this->fields['bibCarrier_txtF_mv'] ?? null;
    }

    /**
     * AK: Get supplement issue(s).
     * 
     * @return array An array or null
     */
    public function getSupplements() {
        return $this->getRelations('supplIssue_txt_mv', true);
    }

    /**
     * AK: Get supplement parent(s).
     * 
     * @return array An array or null
     */
    public function getSupplementParents() {
        return $this->getRelations('supplParent_txt_mv', true);
    }

    /**
     * AK: Get other edition(s).
     * 
     * @return array An array or null
     */
    public function getOtherEditions() {
        return $this->getRelations('otherEdition_txt_mv', true);
    }

    /**
     * AK: Get other physical form(s).
     * 
     * @return array An array or null
     */
    public function getOtherPhysForms() {
        return $this->getRelations('additionalPhysForm_txt_mv', true);
    }

    /**
     * AK: Get "issued with" item(s).
     * 
     * @return array An array or null
     */
    public function getIssuedWith() {
        return $this->getRelations('issuedWith_txt_mv');
    }

    /**
     * AK: Get preceding title(s).
     * 
     * @return array An array or null
     */
    public function getPrecedings() {
        return $this->getRelations('preceding_txt_mv', true,
            '/^\s*vorg\.?:?\s*?-*?>?$/i');
    }

    /**
     * AK: Get succeeding title(s).
     * 
     * @return array An array or null
     */
    public function getSucceedings() {
        return $this->getRelations('succeeding_txt_mv', true,
            '/^\s*forts\.?:?\s*?-*?>?$/i');
    }

    /**
     * AK: Get other relation(s).
     * 
     * @return array An array or null
     */
    public function getOtherRelations() {
        return $this->getRelations('otherRelation_txt_mv', true);
    }

    /**
     * AK: Generic function for getting relations.
     * TODO: Implement a fallback to MarcXML if possible.
     *
     * @param string $solrField The Solr field containing the relation information.
     * It must be a multivalued field. Per relation there has to be exactly 13
     * values. E. g. for 2 relations there need to be 26 values in the multivalued
     * field. The content and order of these values must be: 1. title, 2. main entry,
     * 3. edition, 4. publication data, 5. relation part, 6. physical descriptions,
     * 7. relation info (used for prefix), 8. serial data, 9. note, 10. control no.,
     * 11. ISSN, 12. ISBN, 13. record ID (used for linking).
     * @param boolean $prefix   true if the prefix from "relation info" should be
     *                          displayed to the user, false otherwise.
     * @param string $prefixExclude Only necessary if $prefix is true. Should be a
     * regex pattern (including slashes and optional regex modifiers) that will be
     * applied to the prefix value. If it matches, the prefix will NOT be displayed.
     * This is usefull if you want to avoid showing the same value if e. g. the label
     * in the core details table and the prefix would be the same. Example: /vorg\./i
     * 
     * @return array|null An array with relation data or null
     */
    public function getRelations($solrField, $prefix = false, $prefixExclude = null)
    {
        // Define variables
        $result = [];
        $title = null;
        $mainEntry = null;
        $edition = null;
        $pubData = null;
        $relPart = null;
        $physDesc = null;
        $relInfo = null;
        $serData = null;
        $note = null;
        $ctrlNo = null;
        $issn = null;
        $isbn = null;
        $id = null;

        $solr = $this->fields[$solrField] ?? null;

        if ($solr) {

            $defaultSolrValues = ['NoMainEnt', 'NoEd', 'NoPubData', 'NoRelPart',
                'NoPhysDesc', 'NoRelInfo', 'NoSerData', 'NoNote', 'NoCtrlNo',
                'NoIssn', 'NoIsbn', 'NoId'];

            foreach ($solr as $key => $value) {
    			if (($key % 13) == 0) { // First of 13 values
                    $title = (in_array($value, $defaultSolrValues)) ? null : $value;
    			} else if (($key % 13) == 1) { // Second of 13 values
    				$mainEntry = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
    			} else if (($key % 13) == 2) { // Third of 13 values
    				$edition = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
    			} else if (($key % 13) == 3) {
                    $pubData = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 13) == 4) {
                    $relPart = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 13) == 5) {
                    $physDesc = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 13) == 6) {
                    $relInfo = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 13) == 7) {
                    $serData = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 13) == 8) {
                    $note = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 13) == 9) {
                    $ctrlNo = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 13) == 10) {
                    $issn = (in_array($value, $defaultSolrValues)) ? null : $value;
                } else if (($key % 13) == 11) {
                    $isbn = (in_array($value, $defaultSolrValues)) ? null : $value;
                } else if (($key % 13) == 12) { // Thirteenth and last of 13 values
                    $id = (in_array($value, $defaultSolrValues)) ? null : $value;

                    // We have all values now, add them to the return array:
                    $result[] = ['title' => $title, 'mainEntry' => $mainEntry,
                        'edition' => $edition, 'pubData' => $pubData,
                        'relPart' => $relPart, 'physDesc' => $physDesc,
                        'relInfo' => $relInfo, 'serData' => $serData,
                        'note' => $note, 'ctrlNo' => $ctrlNo, 'issn' => $issn,
                        'isbn' => $isbn, 'id' => $id, 'prefix' => $prefix,
                        'prefixExclude' => $prefixExclude];
                }
    		}
        }

        return empty($result) ? null : $result;
    }

    /**
     * AK: Get local dewey number(s)
     * TODO: Fallback to MarcXML
     * 
     * @return array An array or null
     */
    public function getLocalDewey() {
        return $this->fields['deweyAk_txt_mv'] ?? null;
    }

    /**
     * AK: Get raw dewey number(s)
     * TODO: Fallback to MarcXML
     * 
     * @return array An array or null
     */
    public function getRawDewey() {
        return $this->fields['dewey-raw'] ?? null;
    }

    /**
     * AK: Get all unique dewey number(s)
     * 
     * @return array An array with unique dewey numbers or null
     */
    public function getAllDeweys() {
        $allDeweys = array_unique(
            array_merge(
                $this->getLocalDewey() ?? [],
                $this->getRawDewey() ?? []
            )
        );
        return empty($allDeweys) ? null : $allDeweys;
    }

    /**
     * AK: Get summarized holdings. First we try to find them in our index data in
     * the "fullrecord" field which holds the whole MarcXML data. If no HOL fields
     * were found there, we fall back to the ILS API to find the data.
     *
     * @return array An array with summarized holding data or an empty array
     */
    public function getSummarizedHoldings()
    {
        // Initialize result variable
        $sumHoldings = [];

        // Get summarized holdings from XML
        $sumHolsFromXml = $this->getSummarizedHoldingsFromXml();

        // If we have an array (even an empty one), we can be sure that HOL fields
        // were found in our data and that we can possibly get summarized holdings.
        // Having HOL files in the record does not necessarily mean that they contain
        // summarized holdings, so the array could be empty and that would be OK.
        if (is_array($sumHolsFromXml)) {
            // Convert the holdings we got from the MarcXML in our index data to
            // holdings that resemble the default holdings we get from the ILS
            // driver This also hides holdings from the hide_holdings[] setting in
            // config.ini.
            // Info: Method "summarizedDriverHoldings()" of holdLogic is injected
            // through \AkSearch\RecordDriver\IlsAwareDelegatorFactory
            $sumHolsFromXmlDriverLayout = $this->holdLogic->summarizedDriverHoldings(
                ['summarizedHoldings' => $sumHolsFromXml]
            );

            // Format holdings for use in the holdings tab.
            // Info: Method "formatSummarizedHoldings()" of holdLogic is injected
            // through \AkSearch\RecordDriver\IlsAwareDelegatorFactory
            $sumHoldings = $this->holdLogic->formatSummarizedHoldings(
                $sumHolsFromXmlDriverLayout
            );
        } else if ($sumHolsFromXml == null) {
            // If the result from XML is null it means that we have no HOL fields in
            // the current record. As a fallback measure, we call the ILS API to see
            // if we can get summarized holdings.
            $id = $this->getUniqueID();
            if ($this->ils->checkCapability('getSummarizedHoldings', $id)) {
                // Get summarized holdings from ILS
                if (!empty($sumHolsFromIls = $this->ils->getSummarizedHoldings($id)))
                {
                    // Convert the holdings we got from the ILS to holdings that
                    // resemble the default holdings we get from the ILS driver This
                    // also hides holdings from the hide_holdings[] setting in
                    // config.ini.
                    // Info: Method "summarizedDriverHoldings()" of holdLogic is
                    // injected through
                    // \AkSearch\RecordDriver\IlsAwareDelegatorFactory
                    $sumHolsFromIlsDriverLayout = $this->holdLogic
                        ->summarizedDriverHoldings(['summarizedHoldings' =>
                        $sumHolsFromIls]);

                    // Format holdings for use in the holdings tab.
                    // Info: Method "formatSummarizedHoldings()" of holdLogic is
                    // injected through
                    // \AkSearch\RecordDriver\IlsAwareDelegatorFactory
                    $sumHoldings = $this->holdLogic->formatSummarizedHoldings(
                        $sumHolsFromIlsDriverLayout
                    );
                }
            }
        }

        return $sumHoldings;
    }

    /**
     * AK: Check if this record contains a provenance code.
     * TODO: Get provenance also from Solr index
     *
     * @return boolean True if a provenance code exists, false otherwise
     */
    public function hasItemProvenance($provenanceConfig = null) {
        $hasItemProvenance = $this->hasItemProvenanceFromXml($provenanceConfig);
        return $hasItemProvenance;
    }

    /**
     * AK: Get provenance fields.
     * TODO: Get provenance also from Solr index
     *
     * @return array The item provenance fields as array
     */
    public function getItemProvenance($provenanceConfig = null) {
        $itemProvenance = $this->getItemProvenanceFromXml($provenanceConfig);
        return $itemProvenance;
    }

    /**
     * AK: Get information for record banner(s).
     * TODO: Get information from Solr index
     *
     * @param array $recordBannerConfig Record banner config from config.ini as array
     * 
     * @return array Array with information for record banner (images, texts) or an
     *               empty array if no record banner should be displayed.
     */
    public function getRecordBanner($recordBannerConfig = null) {
        // Return if no config is set
        if ($recordBannerConfig === null) {
            return [];
        }
        
        $recordBanner = $this->getRecordBannerFromXml($recordBannerConfig);
        return $recordBanner;
    }
    
}
