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
    use \VuFind\RecordDriver\MarcBasicTrait {
        getShortTitle as protected getShortTitleFromMarcBasicTrait;
        getSubtitle as protected getSubtitleFromMarcBasicTrait;
        getPhysicalDescriptions
            as protected getPhysicalDescriptionsFromMarcBasicTrait;
    }
    use \VuFind\RecordDriver\MarcAdvancedTrait {
        \VuFind\RecordDriver\MarcAdvancedTrait::getNewerTitles insteadof
            \VuFind\RecordDriver\MarcBasicTrait;
        \VuFind\RecordDriver\MarcAdvancedTrait::getPreviousTitles insteadof
            \VuFind\RecordDriver\MarcBasicTrait;
    }

    /**
     * Get field 515a (Numbering Peculiarities Note)
     *
     * @return array    An array of values in 515a
     */
    public function getNumberingNotes() {
        return $this->getFieldArray('515', ['a'], true, '; ');
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
                    $subfields689 = $subjectField689->getSubfields('[axvtyzbcg]',
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

        return array_values(array_unique($this->stripNonSortingChars($returnValue)));
    }

    /**
     * AK: This function is used to get subjects for keyword chains.
     *     We get all subject headings associated with this record. Keyword chains
     *     are built from 689 fields which is a specific field used by
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
                $subfields = $subjectField->getSubfields('[axvtyzbcg]', true);
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

        return $this->stripNonSortingChars($returnValue);
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
     * Get the short (pre-subtitle) title of the record.
     * 
     * AK: Remove non-sorting-characters
     *
     * @return string
     */
    public function getShortTitle()
    {
        return $this->stripNonSortingChars($this->getShortTitleFromMarcBasicTrait());
    }

    /**
     * Get the subtitle of the record.
     * 
     * AK: Remove non-sorting-characters
     *
     * @return string
     */
    public function getSubtitle()
    {
        return $this->stripNonSortingChars($this->getSubtitleFromMarcBasicTrait());
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
     *     subfield v. Returns volume numbers for journal articles or chapters. For
     *     volume numbers of series items or multivolume items, see getSeriesVolume()
     *     below.
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
     * AK: Get a volume number if available. Returns volume numbers of series items
     *     or multivolume items. For volume numbers of journal articles or chapters,
     *     see getContainerVolume() above.
     *
     * @return string|null   The volume number of a monographic item or null
     */
    public function getSeriesVolume() {
        $volNo = null;
        // Check if we have a "part" record. If not, the current record could be
        // a volume from a series or multivolume work.
        if (strpos(strtolower($this->getBibliographicLevel()), 'part') === false) {
            $volNos = $this->getFieldArray('PNT', ['7', '8', '2', '4'], false) ?: null;
            $volNo = !empty($volNos) ? $volNos[0] : null;
        }
        return $volNo;
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
        return empty($secondary) ? [] : $this->stripNonSortingChars($secondary);
    }

    /**
     * AK: Get notes on participants or performers from field 511.
     *
     * @return array An array with notes on participants or performers from field 511
     */
    public function getParticipantPerformerNotes() {
        return $this->getFieldArray('511', ['a']);
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
     * Get publication details from 264 fields. This function take several variants
     * of subfield notation into account, like e. g. multiple subfields a and b.
     * 
     * For these special cases in austrian libraries, see:
     * https://wiki.obvsg.at/Katalogisierungshandbuch/Kategorienuebersicht264FE
     *
     * @return array
     */
    public function getAkPublicationDetails() {
        // Create result array as return value
        $result = [];

        // Get all fields 264 and add their data to an easy-to-process array
        $fs264 = [];
        $fs264FileMarc = $this->getMarcRecord()->getFields('264');
        foreach ($fs264FileMarc as $f264FileMarc) {
            $ind1  = $f264FileMarc->getIndicator('1');
            $ind2  = $f264FileMarc->getIndicator('2');
            $subfs = [];
            foreach ($f264FileMarc->getSubfields() as $sf) {
                $subfs[] = [$sf->getCode() => $sf->getData()];
            }
            $fs264[] = ['ind1' => $ind1, 'ind2' => $ind2, 'subfs' => $subfs];
        }

        // Iterate over each field 264
        foreach ($fs264 as $f264) {
            $subfieldResult = [];

            // Get subfields
            $subfs = $f264['subfs'];

            // Array columns of subfields a, b and c            
            $subfsA = array_column($subfs, 'a');
            $subfsB = array_column($subfs, 'b');
            $subfsC = array_column($subfs, 'c');

            // Join subfields c (= dates) to a string
            $dates  = (!empty($subfsC)) ? join(', ', $subfsC) : null;

            // Check if subfields a and b exists
            if (!empty($subfsA) && !empty($subfsB)) {

                // Create pairs of subfields a (= place) and b (= publisher name) if
                // their counts are the same. The result is a colon separated string
                // like: "Place : Publisher Name"
                if (count($subfsA) === count($subfsB)) {
                    $size = count($subfsA);
                    for ($i = 0; $i < $size; $i++) {
                        $subfieldResult[] = $subfsA[$i] . ' : ' . $subfsB[$i];
                    }
                } else {
                    // If the count is of subfields a and b is not the same, just
                    // join them separately and then join them again, separated by
                    // a colon.
                    $subfieldResult[] = join(', ', $subfsA) . ' : ' . join(', ',
                        $subfsB);
                }
            } else {
                // If subfield a or b doesn't exist, join just the existing one
                if (!empty($subfsA)) {
                    $subfieldResult[] = join(', ', $subfsA);
                }
                if (!empty($subfsB)) {
                    $subfieldResult[] = join(', ', $subfsB);
                }
            }

            // If dates exist, add them as last item to the array            
            if ($dates != null) {
                $subfieldResult[] = $dates;
            }

            // Create result array if we have results
            if (!empty($subfieldResult)) {
                $result[] = [
                    // Add indicators to the return array. This makes it possible to
                    // display the different meanings the publication details could
                    // have to the user.
                    'ind1' => $f264['ind1'],
                    'ind2' => $f264['ind2'],
                    // Join the processed data from the subfields of one single field
                    // 264 to a comma separated string.
                    'data' => join(', ', $subfieldResult)
                ];
            }
        }

        return $result;
    }

    /**
     * AK: Get physical description separated by colon
     *
     * @return array
     */
    public function getPhysicalDescriptions()
    {
        return $this->getFieldArray('300', ['a', 'b', 'c', 'e', 'f', 'g'], true,
            '; ');
    }

    /**
     * AK: Check if the MarcXML record has parent records by checking if PNT tags
     * exists.
     *
     * @return boolean  True if parent records exists, false otherwise
     */
    public function hasParents() {
        return empty($this->getFieldArray('PNT')) ? false : true;
    }

    /**
     * AK: Check if the MarcXML record has child records by checking if CLD tags
     * exists.
     *
     * @return boolean  True if child records exists, false otherwise
     */
    public function hasChilds() {
        return empty($this->getFieldArray('CLD')) ? false : true;
    }

    /**
     * AK: Get summarized holdings and add it to the holdings array that is returned
     * from the default Alma ILS driver. This is quite specific to Austrian
     * libraries. See below for information on used MARC fields
     * 
     * TODO:
     *  - Less nesting in code below.
     *  - Fields 852b and 852c are not repeated in Austrian libraries, but we should
     *    consider the fact that these fields are repeatable according to the
     *    official Marc21 documentation.
     *  
     * Marc holding field 852 (= "HOL" field in our indexed data)
     * See https://wiki.obvsg.at/Katalogisierungshandbuch/KategorienuebersichtB852FE
     * - Library Code:      tag=HOL ind1=8 ind2=1|# subfield=b
     * - Location:          tag=HOL ind1=8 ind2=1|# subfield=c
     * - Call No.:          tag=HOL ind1=8 ind2=1|# subfield=h
     * - Note on call no.:  tag=HOL ind1=8 ind2=1|# subfield=k
     * 
     * Marc holding field 866 (= "HOL" field in our indexed data)
     * See https://wiki.obvsg.at/Katalogisierungshandbuch/KategorienuebersichtB866FE
     * - Summarized holdings:                   tag=HOL ind1=3 ind2=0 subfield=a
     * - Gaps:                                  tag=HOL ind1=3 ind2=0 subfield=z
     * - Prefix text for summarized holdings:   tag=HOL ind1=# ind2=0 subfield=a
     * - Note for summarized holdings:          tag=HOL ind1=# ind2=0 subfield=z
     * 
     * @param [type] $id
     * @param [type] $patron
     * @param array $options
     * @return array
     */
    public function getSummarizedHoldings() {
        // Initialize variables
        $summarizedHoldings = [];

        // Get all HOL fields from the MarcXML record
        $holFields = $this->getMarcRecord()->getFields('HOL');

        // Return null if there is no HOL field
        if (empty($holFields)) {
            return null;
        }

        // Group the fields by holding ID. This way we have one array that includes
        // all related fields (852 and 866 fields of the same holdings record).
        $groupedHols = [];
        foreach ($holFields as $holField) {
            $holId = $holField->getSubfield('8')->getData();
            $groupedHols[$holId][] = $holField;
        }

        foreach ($groupedHols as $holId => $groupedHol) {
            // Check if we have a HOL field with value "3" in ind1. This indicates
            // that we have summarized holding information.
            $hasSumHols = false;
            foreach ($groupedHol as $holField) {
                if ($holField->getIndicator('1')=='3') {
                    $hasSumHols = true;
                    break;
                }
            }

            if ($hasSumHols) {
                $libraryCode = null;
                $locationCode = null;
                $callNo = null;
                $callNoNote = null;
                $sumHoldings = null;
                $gaps = null;
                $sumHoldingsPrefix = null;
                $sumHoldingsNote = null;

                foreach ($groupedHol as $holField) {
                    // Process HOL field(s) with value "8" in ind1. These are values
                    // from the 852 field.
                    if ($holField->getIndicator('1')=='8') {
                        // Add data from subfields to arrays as their key for having
                        // unique values. We just use 'true' as array values.
                        foreach ($holField->getSubfields('b') as $HOLb) {
                            $libraryCode[$HOLb->getData()] = true;
                        }
                        foreach ($holField->getSubfields('c') as $HOLc) {
                            $locationCode[$HOLc->getData()] = true;
                        }
                        foreach ($holField->getSubfields('h') as $HOLh) {
                            $callNo[$HOLh->getData()] = true;
                        }
                        foreach ($holField->getSubfields('k') as $HOLk) {
                            $callNoNote[$HOLk->getData()] = true;
                        }
                    }
                    // Process HOL field(s) with value "3" in ind1. These are values
                    // from the 866 field.
                    if ($holField->getIndicator('1') == '3') {
                        foreach ($holField->getSubfields('a') as $HOL30a) {
                            $sumHoldings[$HOL30a->getData()] = true;
                        }
                        foreach ($holField->getSubfields('z') as $HOL30z) {
                            $gaps[$HOL30z->getData()] = true;
                        }
                    }
                    // Process HOL field(s) with value "blank" in ind1. These are
                    // values from the 866 field.
                    if ($holField->getIndicator('1') == ' ') {
                        foreach ($holField->getSubfields('a') as $HOL_0a) {
                            $sumHoldingsPrefix[$HOL_0a->getData()] = true;
                        }
                        foreach ($holField->getSubfields('z') as $HOL_0z) {
                            $sumHoldingsNote[$HOL_0z->getData()] = true;
                        }
                    }
                }

                $summarizedHoldings[] = [
                    'library' => ($libraryCode)
                        ? implode(', ', array_keys($libraryCode))
                        : null,
                    'location' => ($locationCode)
                        ? implode(', ', array_keys($locationCode))
                        : 'UNASSIGNED',
                    'callnumber' => ($callNo)
                        ? implode(', ', array_keys($callNo))
                        : null,
                    'callnumber_notes' => ($callNoNote)
                        ? array_keys($callNoNote)
                        : null,
                    'holdings_available' => ($sumHoldings)
                        ? implode(', ', array_keys($sumHoldings))
                        : null,
                    'gaps' => ($gaps)? array_keys($gaps) : null,
                    'holdings_prefix' => ($sumHoldingsPrefix)
                        ? implode(', ', array_keys($sumHoldingsPrefix))
                        : null,
                    'holdings_notes' => ($sumHoldingsNote)
                        ? array_keys($sumHoldingsNote)
                        : null
                ];
            }
        }

        return $summarizedHoldings;
    }

    /**
     * AK: Callback function for array_filter function.
     * Default array_filter would not only filter out empty or null values, but also
     * the number "0" (as it evaluates to false). So if a value (e. g. a title) would
     * just be "0" it would not be displayed.
     *
     * @param   string $var The value of an array. In our case these are strings.
     * 
     * @return  boolean     False if $var is null or empty, true otherwise.
     */
    public function filterCallback($var)
    {
        // Return false if $var is null or empty
        if ($var == null || trim($var) == '') {
            return false;
        }
        return true;
    }

    /**
     * AK: Remove non-sorting-characters "<<" and ">>" from the provided data. The
     * data can be a string or an array.
     *
     * @param   String|array $data  That data from which the non-sorting-characters
     *                              should be removed.
     * 
     * @return  String|array        String or array without non-sorting-characters
     */
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
