<?php
/**
 * AK: Extended functions to add advanced MARC-driven functionality to a record
 * driver already powered by the standard index spec. Depends upon MarcReaderTrait.
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
 * AK: Extending functions to add advanced MARC-driven functionality to a record
 * driver already powered by the standard index spec. Depends upon MarcReaderTrait.
 *
 * @category AKsearch
 * @package  RecordDrivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait MarcAdvancedTrait
{
    use \VuFind\RecordDriver\MarcBasicTrait, \VuFind\RecordDriver\MarcAdvancedTrait {
        \VuFind\RecordDriver\MarcAdvancedTrait::getNewerTitles insteadof
            \VuFind\RecordDriver\MarcBasicTrait;
        \VuFind\RecordDriver\MarcAdvancedTrait::getPreviousTitles insteadof
            \VuFind\RecordDriver\MarcBasicTrait;
    }

    /**
     * Get all distinct subject terms as an array. We use fields 689 and 982. We
     * don't return terms that are names of persons or corporations. We are only
     * getting subjects with this function.
     *
     * @return array    An array of subject terms
     */
    public function getAllSubjects()
    {
        $returnValue = [];

        // 689 fields
        $subjectFields689 = $this->getMarcRecord()->getFields('689');
        foreach($subjectFields689 as $subjectField689) {
            $ind1 = $subjectField689->getIndicator(1);
            $ind2 = $subjectField689->getIndicator(2);
            if (is_numeric($ind1) && is_numeric($ind2)) {
                $subjectType689 = ($subjectField689->getSubfield('D'))
                    ? $subjectField689->getSubfield('D')->getData()
                    : null;
                // Use only subject terms (designated by "s"), not e. g. person names
                if ($subjectType689 == 's' || $subjectType689 == 'S') {
                    $subfields689 = $subjectField689->getSubfields('[axvyzbcg]',
                        true);
                    $subfieldData689 = [];
                    foreach($subfields689 as $subfield689) {
                        $subfieldData689[] = $subfield689->getData();
                    }
                    $returnValue[] = join(', ', $subfieldData689);
                }
            }
        }

        // 982 fields
        $subjectFields982  = $this->getMarcRecord()->getFields('982');
        foreach($subjectFields982 as $subjectField982) {
            // Don't use subfields with person or corporate names in it ("d" and
            // "e"). We only want subject terms.
            $subfields982 = $subjectField982->getSubfields('[abcfz]', true);
            if (!empty($subfields982)) {
                foreach($subfields982 as $subfield982) {
                    $subfieldData982 = $subfield982->getData();
                    $tokens982 = preg_split("/\s+[\/-]\s+/", $subfieldData982);
                    foreach($tokens982 as $token982) {
                        $returnValue[] = $token982;
                    }
                }
            }
        }

        return array_values(array_unique($returnValue));
    }

    /**
     * AK: This function is used to get subjects for keyword chains.
     *     We get all subject headings associated with this record. Keyword chains
     *     are buildt from 689 fields which is a specific field used by
     *     german-speaking libraries. We also have field 982 which is unique for some
     *     austrian libraries.
     *     TODO: Maybe we could use config to specify the fields that should be used
     *     for keyword chains. That would make it more flexible for libraries that
     *     are using other Marc fields.
     * 
     * @param bool $extended AK: Has no functionality here. Only exists to be
     *                       compatible with the function "getAllSubjectHeadings" in
     *                       \VuFind\RecordDriver\MarcAdvancedTrait
     *
     * @return array
     */
    public function getAllSubjectHeadings($extended = false)
    {
        $returnValue = [];
        $subjectFields = $this->getMarcRecord()->getFields('689');

        $ind1 = 0;
        foreach($subjectFields as $subjectField) {
            $ind1 = $subjectField->getIndicator(1);
            $ind2 = $subjectField->getIndicator(2);

            if (is_numeric($ind1) && is_numeric($ind2)) {
                $subfields = $subjectField->getSubfields('[axvyzbcg]', true);
                $subfieldData = [];
                foreach($subfields as $subfield) {
                    $subfieldData[] = $subfield->getData();
                }
                $returnValue[$ind1][$ind2] = (join(', ', $subfieldData));
            }
        }

        $subjectFields982  = $this->getMarcRecord()->getFields('982');
        $fieldCount = $ind1+1;
        foreach($subjectFields982 as $subjectField982) {
            $ind1 = $subjectField982->getIndicator(1);
            $ind2 = $subjectField982->getIndicator(2);
            if (empty(trim($ind1)) && empty(trim($ind2))) {
                
                $subfields = $subjectField982->getSubfields('a', false);
                if (!empty($subfields)) {
                    $subfieldData = [];
                    $tokenCount = 0;
                    foreach($subfields as $subfield) {
                        $subfieldContent = $subfield->getData();
                        $tokens = preg_split("/\s+[\/-]\s+/", $subfieldContent);
                        foreach($tokens as $token) {
                            $returnValue[$fieldCount][$tokenCount] = $token;
                            $tokenCount++;
                        }
                    }
                    $fieldCount++;
                }
            }
        }

        $returnValue = array_map(
            'unserialize', array_unique(array_map('serialize', $returnValue))
        );

        return $returnValue;
    }

    /**
     * Get the full title of the record.
     * 
     * AK: Separate by colon
     *
     * @return string
     */
    public function getTitle()
    {
        $matches = $this->getFieldArray('245', ['a', 'b'], true, ' : ');
        return (is_array($matches) && count($matches) > 0) ?
            $this->stripNonSortingChars($matches[0]) : null;
    }

    /**
     * AK: Get the full title of the container. This is the main title and the
     *     subtitle separated by colon.
     *
     * @return string
     */
    public function getContainerTitle()
    {
        $matches = $this->getFieldArray('PNT', ['a', 'b'], true, ' : ');
        return (is_array($matches) && count($matches) > 0) ?
        $this->stripNonSortingChars($matches[0]) : null;
    }

    /**
     * Get the text of the part/section portion of the title.
     * 
     * AK: Separate by colon
     * 
     * @return string
     */
    public function getTitleSection()
    {
        $matches = $this->getFieldArray('245', ['n', 'p'], true, ' : ');
        return (is_array($matches) && count($matches) > 0) ?
            $this->stripNonSortingChars($matches[0]) : null;
    }

    /**
     * AK: Get the text of the part/section portion of the container title, separated
     *     by colon.
     * 
     * @return string
     */
    public function getContainerTitleSection()
    {
        $matches = $this->getFieldArray('PNT', ['n', 'p'], true, ' : ');
        return (is_array($matches) && count($matches) > 0) ?
            $this->stripNonSortingChars($matches[0]) : null;
    }

    /**
     * AK: Get the whole title of the record. This is the main title, subtitle and
     *     title sections, separated by colon. Info: With getTitle, we already get
     *     the main title (245a) and the subtitle (245b), already separated by colon.
     *
     * @return string The whole title with it's parts separated by colon
     */
    public function getWholeTitle()
    {
        // AK: Join the title and title section together. With array_filter we remove
        // possible empty values.
        return implode(
            ' : ',
            array_filter(
                [trim($this->getTitle()), trim($this->getTitleSection())],
                array($this, 'filterCallback')
            )
        );
    }

    /**
     * AK: Get the whole title of the container. This is the main title, subtitle and
     *     title sections, separated by colon. Info: With getContainerTitle, we
     *     already get the main title (245a) and the subtitle (245b), already
     *     separated by colon.
     *
     * @return string
     */
    public function getWholeContainerTitle()
    {
        // AK: Join the title and title section together. With array_filter we remove
        //     possible empty values.
        return implode(
            ' : ',
            array_filter(
                [
                    trim($this->getContainerTitle()),
                    trim($this->getContainerTitleSection())
                ],
                array($this, 'filterCallback')
            )
        );
    }

    /**
     * AK: Get a volume number if available. This is the value from datafield VAR,
     *     subfield v
     *
     * @return string   The value from datafield VAR, subfield v
     */
    public function getContainerVolume()
    {
        return $this->getFirstFieldValue('VAR', ['v']);
    }

    /**
     * AK: Get an issue number if available. This is the value from datafield VAR,
     *     subfield i
     *
     * @return string   The value from datafield VAR, subfield i
     */
    public function getContainerIssue()
    {
        return $this->getFirstFieldValue('VAR', ['i']);
    }

    /**
     * AK: Get the pages if available. This is the value from datafield VAR,
     *     subfield p
     *
     * @return string   The value from datafield VAR, subfield p
     */
    public function getContainerPageRange()
    {
        return $this->getFirstFieldValue('VAR', ['p']);
    }

    /**
     * AK: Get the first page if available. This is parsed from the value in
     *     datafield VAR, subfield p
     *
     * @return string   The start page, paresed from datafield VAR, subfield p
     */
    public function getContainerStartPage()
    {
        $startPage = null;
        $pages = $this->getContainerPageRange();
        if ($pages) {
            $arr = preg_split('/\s*-\s*/', $pages);
            $startPage = (count($arr)>0) ? trim($arr[0]) : null;
        }
        return $startPage;
    }

    /**
     * AK: Get the end page if available. This is parsed from the value in datafield
     *     VAR, subfield p
     *
     * @return string   The end page, parsed from datafield VAR, subfield p
     */
    public function getContainerEndPage()
    {
        $endPage = null;
        $pages = $this->getContainerPageRange();
        if ($pages) {
            $arr = preg_split('/\s*-\s*/', $pages);
            $endPage = (count($arr)>1) ? trim($arr[1]) : null;
        }
        return $endPage;
    }
    
    /**
     * AK: Get the main author of the record from field 100 or 700. Don't get dates
     *     from subfield 'd' and remove non-sorting-characters.
     *
     * @return array An array with the first author name or an empty array
     */
    public function getPrimaryAuthors()
    {
        $primary = $this->getFieldArray('100', ['a', 'b', 'c']);
        /*
        $primary = (empty($primary))
            ? $this->getFieldArray('700', ['a', 'b', 'c'])
            : $primary;
        */
        return empty($primary) ? [] : $this->stripNonSortingChars([$primary[0]]);
    }

    /**
     * AK: Get the main author of the record as a string. Dates from subfield 'd' are
     *     removed. Also non-sorting-characters are removed.
     *
     * @return string|null The author name as string or null
     */
    public function getPrimaryAuthor()
    {
        $authors = $this->getPrimaryAuthors();
        return $authors[0] ?? null;
    }

    /**
     * AK: Get the main corporate author without date from subfield 'd'.
     *     Non-sorting-characters are removed.
     *
     * @return array    An array with the first corporate author name or an empty
     *                  array
     */
    public function getCorporateAuthors()
    {
        $corps = $this->getFieldArray('110', ['a', 'b', 'c']);
        /*
        $corps = (empty($corps))
            ? $this->getFieldArray('710', ['a', 'b', 'c'])
            : $corps;
        */
        return empty($corps) ? [] : $this->stripNonSortingChars([$corps[0]]);
    }
    
    /**
     * AK: Get the first corporate author without date from subfield 'd'. Non-sorting
     *     characters are already removed in getCorporateAuthors() (see above).
     *
     * @return String|null The author name as string or null
     */
    public function getCorporateAuthor()
    {
        $corpAuthors = $this->getCorporateAuthors();
        return $corpAuthors[0] ?? null;
    }
    
    /**
     * AK: Get the first meeting name. Non-sorting-characters are removed.
     *
     * @return array    An array with the first meeting name or an empty array
     */
    public function getMeetingAuthors()
    {
        $meetings = $this->getFieldArray('111', ['a', 'c', 'd', 'e']);
        /*
        $meetings = (empty($meetings))
            ? $this->getFieldArray('711', ['a', 'c', 'd', 'e'])
            : $meetings;
        */
        return empty($meeting) ? [] : $this->stripNonSortingChars([$meetings[0]]);
    }

    /**
     * AK: Get the first meeting name of all meeting names in Marc fields 111 or 711.
     *     Non-sorting-characters are already removed in getMeetingNames() (see
     *     above).
     *
     * @return String|null The first meeting name as string or null
     */
    public function getMeetingAuthor()
    {
        $meetings = $this->getMeetingAuthors();
        return $meetings[0] ?? null;
    }

    /**
     * AK: Get an array of all secondary authors. Don't get dates from subfield 'd'.
     *     Non-sorting-characters are removed.
     *
     * @return array An array with all secondary authors or an empty array.
     */
    public function getSecondaryAuthors()
    {
        $secondary = $this->getFieldArray('700', ['a', 'b', 'c']);
        /*
        // Check if there is a 100 field. If not, the first entry in a 700 field is
        // already used as the primary author, so we need to skip that one here as
        // otherwise we would produce a duplicate.
        $hasPrimary = (empty($this->getFieldArray('100', ['a', 'b', 'c'])))
            ? false
            : true; 
        if (!empty($secondary) && !$hasPrimary) {
            unset($secondary[0]);
        }
        */
        return empty($secondary) ? [] : $this->stripNonSortingChars($secondary);
    }   

    /**
     * AK: Get an array of all secondary corporate authors. Don't get dates from
     *     subfield 'd'. Non-sorting-characters are removed.
     *
     * @return array An array with all secondary corporate authors or an empty array.
     */
    public function getSecondaryCorporateAuthors()
    {
        $secondary = $this->getFieldArray('710', ['a', 'b', 'c']);
        /*
        // Check if there is a 110 field. If not, the first entry in a 710 field is
        // already used as the primary corporate author, so we need to skip that one
        // here as otherwise we would produce a duplicate.
        $hasPrimary = (empty($this->getFieldArray('110', ['a', 'b', 'c'])))
            ? false
            : true; 
        if (!empty($secondary) && !$hasPrimary) {
            unset($secondary[0]);
        }
        */
        return empty($secondary) ? [] : $this->stripNonSortingChars($secondary);
    }

    /**
     * AK: Get an array of all secondary meeting names.
     *
     * @return array An array with all secondary meeting names or an empty array.
     */
    public function getSecondaryMeetingAuthors()
    {
        $secondary = $this->getFieldArray('711', ['a', 'c', 'd', 'e']);
        /*
        // Check if there is a 111 field. If not, the first entry in a 711 field is
        // already used as the primary meeting name, so we need to skip that one
        // here as otherwise we would produce a duplicate.
        $hasPrimary = (empty($this->getFieldArray('111', ['a', 'c', 'd', 'e'])))
            ? false
            : true; 
        if (!empty($secondary) && !$hasPrimary) {
            unset($secondary[0]);
        }
        */
        return empty($secondary) ? [] : $this->stripNonSortingChars($secondary);
    }

    /**
     * AK: Get an array of all genres.
     *
     * @return array
     */
    public function getGenres()
    {
        return $this->getFieldArray('655', ['a']);
    }

    /**
     * AK: Get an array of all dissertation notes.
     *
     * @return array
     */
    public function getDissertationNotes()
    {
        return $this->getFieldArray('502', ['a']);
    }

    /**
     * Get the item's publication information
     * 
     * AK: We overwrite the function with the same name in \VuFind\RecordDriver\
     * MarcReaderTrait. We also use fields with indicator 3.
     *
     * @param string $subfield The subfield to retrieve ('a' = location, 'c' = date)
     *
     * @return array
     */
    protected function getPublicationInfo($subfield = 'a')
    {
        // Get string separator for publication information:
        $separator = isset($this->mainConfig->Record->marcPublicationInfoSeparator)
            ? $this->mainConfig->Record->marcPublicationInfoSeparator : ' ';

        // First check old-style 260 field:
        $results = $this->getFieldArray('260', [$subfield], true, $separator);

        // Now track down relevant RDA-style 264 fields; we only care about
        // copyright and publication places (and ignore copyright places if
        // publication places are present).  This behavior is designed to be
        // consistent with default SolrMarc handling of names/dates.
        // AK: We also use values in fields with indicator 3 (manufacture places and
        // dates) as we have some of them in our data. See also original code in
        // \VuFind\RecordDriver\MarcReaderTrait
        $pubResults = $copyResults = [];

        $fields = $this->getMarcRecord()->getFields('264');
        if (is_array($fields)) {
            foreach ($fields as $currentField) {
                $currentVal = $this
                    ->getSubfieldArray($currentField, [$subfield], true, $separator);
                if (!empty($currentVal)) {
                    switch ($currentField->getIndicator('2')) {
                    // AK: We also use indicator 3. The code in \VuFind\RecordDriver\
                    // MarcReaderTrait only uses indicator 1.
                    case '1':
                    case '3':
                        $pubResults = array_merge($pubResults, $currentVal);
                        break;
                    case '4':
                        $copyResults = array_merge($copyResults, $currentVal);
                        break;
                    }
                }
            }
        }
        $replace260 = isset($this->mainConfig->Record->replaceMarc260)
            ? $this->mainConfig->Record->replaceMarc260 : false;
        if (count($pubResults) > 0) {
            return $replace260 ? $pubResults : array_merge($results, $pubResults);
        } elseif (count($copyResults) > 0) {
            return $replace260 ? $copyResults : array_merge($results, $copyResults);
        }

        return $results;
    }

    /**
     * AK: Callback function for array_filter function in getWholeTitle method.
     * Default array_filter would not only filter out empty or null values, but also
     * the number "0" (as it evaluates to false). So if a title would just be "0" it
     * would not be displayed.
     *
     * @param   string $var The value of an array. In our case these are strings.
     * 
     * @return  boolean     False if $var is null or empty, true otherwise.
     */
    protected function filterCallback($var)
    {
        // Return false if $var is null or empty
        if ($var == null || trim($var) == '') {
            return false;
        }
        return true;
    }

    protected function stripNonSortingChars($data) {
        if (is_string($data)) {
            return preg_replace('/<<|>>/', '', $data);
        }

        if (is_array($data)) {
            return array_map(
                function($value) {
                    return preg_replace('/<<|>>/', '', $value);
                },
                $data
            );
        }
    }

}
