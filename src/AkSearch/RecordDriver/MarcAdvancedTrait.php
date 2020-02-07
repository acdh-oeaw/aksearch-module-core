<?php
/**
 * AK: Extended functions to add advanced MARC-driven functionality to a record
 * driver already powered by the standard index spec. Depends upon MarcReaderTrait.
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


    public function getAllSubjects() {
        $returnValue = [];

        // 689 fields
        $subjectFields689 = $this->getMarcRecord()->getFields('689');
        foreach($subjectFields689 as $subjectField689) {
            $ind1 = $subjectField689->getIndicator(1);
            $ind2 = $subjectField689->getIndicator(2);
            if (is_numeric($ind1) && is_numeric($ind2)) {
                $subjectType689 = $subjectField689->getSubfield('D')->getData();
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
     * AK: Get all subject headings associated with this record. Keyword chains are
     *     buildt from 689 fields which is a specific field used by libraries in the
     *     german-speeking world.
     * 
     * @param bool $extended AK: Has no functionality here. Only exists to be
     *                       compatible with the function "getAllSubjectHeadings" in
     *                       \VuFind\RecordDriver\MarcAdvancedTrait
     *
     * @return array
     */
    public function getAllSubjectHeadings($extended = false) {
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
            $matches[0] : null;
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
            $matches[0] : null;
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
            $matches[0] : null;
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
            $matches[0] : null;
    }

    /**
     * AK: Get the whole title of the record. This is the main title, subtitle and
     *     title sections, separated by colon. Info: With getTitle, we already get
     *     the main title (245a) and the subtitle (245b), already separated by colon.
     *
     * @return string The whole title with it's parts separated by colon
     */
    public function getWholeTitle() {
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
    public function getWholeContainerTitle() {
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
    public function getContainerVolume() {
        return $this->getFirstFieldValue('VAR', ['v']);
    }

    /**
     * AK: Get an issue number if available. This is the value from datafield VAR,
     *     subfield i
     *
     * @return string   The value from datafield VAR, subfield i
     */
    public function getContainerIssue() {
        return $this->getFirstFieldValue('VAR', ['i']);
    }

    /**
     * AK: Get the pages if available. This is the value from datafield VAR,
     *     subfield p
     *
     * @return string   The value from datafield VAR, subfield p
     */
    public function getContainerPageRange() {
        return $this->getFirstFieldValue('VAR', ['p']);
    }

    /**
     * AK: Get the first page if available. This is parsed from the value in
     *     datafield VAR, subfield p
     *
     * @return string   The start page, paresed from datafield VAR, subfield p
     */
    public function getContainerStartPage() {
        $startPage = null;
        $pages = $this->getContainerPageRange();
        if ($pages) {
            $arr = preg_split('/\s*-\s*/', $pages);
            $startPage = (count($arr)>0) ? trim($arr[0]) : null;
        }
        return $startPage;
    }

    /**
     * AK: Get the end page if available. This is parsed from the value in
     *     datafield VAR, subfield p
     *
     * @return string   The end page, parsed from datafield VAR, subfield p
     */
    public function getContainerEndPage() {
        $endPage = null;
        $pages = $this->getContainerPageRange();
        if ($pages) {
            $arr = preg_split('/\s*-\s*/', $pages);
            $endPage = (count($arr)>1) ? trim($arr[1]) : null;
        }
        return $endPage;
    }
    
    /**
     * Get the main authors of the record.
     * 
     * AK: Don't get dates from subfield 'd'
     *
     * @return array
     */
    public function getPrimaryAuthorsWithoutDate()
    {
        $primary = $this->getFirstFieldValue('100', ['a', 'b', 'c']);
        return empty($primary) ? [] : [$primary];
    }

    /**
     * Get the main author of the record.
     * 
     * AK: Don't get dates from subfield 'd'
     *
     * @return string
     */
    public function getPrimaryAuthorWithoutDate()
    {
        $authors = $this->getPrimaryAuthorsWithoutDate();
        return $authors[0] ?? null;
    }

    /**
     * AK: Get an array of all primary corporate authors without date from
     *     subfield 'd'.
     *
     * @return array
     */
    public function getPrimaryCorporateAuthorsWithoutDate() {
        $primaryCorp = array_merge(
            $this->getFieldArray('110', ['a', 'b', 'c']),
            $this->getPrimaryMeetingNames()
        );
        return empty($primaryCorp) ? [] : [$primaryCorp[0]];
    }

    public function getPrimaryCorporateAuthorWithoutDate() {
        $corpAuthors = $this->getPrimaryCorporateAuthorsWithoutDate();
        return $corpAuthors[0] ?? null;
    }

    public function getPrimaryMeetingNames() {
        $meetings = $this->getFirstFieldValue('111', ['a', 'c', 'd', 'e']);
        return empty($meetings) ? [] : [$meetings];
    }

    public function getPrimaryMeetingName() {
        $meetings = $this->getPrimaryMeetingNames();
        return $meetings[0] ?? null;
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthors()).
     * 
     * AK: Don't get dates from subfield 'd'
     *
     * @return array
     */
    public function getSecondaryAuthorsWithoutDate()
    {
        return $this->getFieldArray('700', ['a', 'b', 'c']);
    }

    /**
     * AK: Get an array of all secondary corporate authors without date from
     *     subfield 'd'.
     *
     * @return array
     */
    public function getSecondaryCorporateAuthorsWithoutDate() {
        return array_merge(
            $this->getFieldArray('710', ['a', 'b', 'c']),
            $this->getSecondaryMeetingNames()
        );
    }

    public function getSecondaryMeetingNames() {
        return $this->getFieldArray('711', ['a', 'c', 'd', 'e']);
    }

    public function getGenres() {
        return $this->getFieldArray('655', ['a']);
    }

    public function getDissertationNotes() {
        return $this->getFieldArray('502', ['a']);
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
    protected function filterCallback($var) {
        // Return false if $var is null or empty
        if ($var == null || trim($var) == '') {
            return false;
        }
        return true;
    }

}
