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

    /**
     * AK: Get all subject headings associated with this record. Keyword chains are
     *     buildt from 689 fields which is a specific field used by libraries in the
     *     german-speeking world.
     * 
     * @param bool $extended Has no functionality here. Only exists to be compatible
     *                       with the function "getAllSubjectHeadings" in
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
     * AK: We use only subfield "a" for the main title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getFirstFieldValue('245', ['a']);
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
     * AK: Get the whole title of the record. This is the main title, subtitle and
     *     title sections, separated by colon.
     *
     * @return string The whole title with it's parts separated by colon
     */
    public function getWholeTitle() {
        $titleMain = trim($this->getTitle());
        $titleSub = trim($this->getSubtitle());
        $titleSec = trim($this->getTitleSection());
        return implode(
            ' : ',
            array_filter(
                [$titleMain, $titleSub, $titleSec],
                array($this, 'filterCallback')
            )
        );
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
