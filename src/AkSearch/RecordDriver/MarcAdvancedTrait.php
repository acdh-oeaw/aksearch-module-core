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

    use \VuFind\RecordDriver\MarcAdvancedTrait;

    /**
     * Fields that may contain subject headings, and their descriptions
     *
     * @var array
     */
    protected $subjectFields = [
        '600' => 'personal name',
        '610' => 'corporate name',
        '611' => 'meeting name',
        '630' => 'uniform title',
        '648' => 'chronological',
        '650' => 'topic',
        '651' => 'geographic',
        '653' => '',
        '655' => 'genre/form',
        '656' => 'occupation'
    ];

    /**
     * Mappings from subject source indicators (2nd indicator of subject fields in
     * MARC 21) to the their codes.
     *
     * @var  array
     * @link https://www.loc.gov/marc/bibliographic/bd6xx.html     Subject field docs
     * @link https://www.loc.gov/standards/sourcelist/subject.html Code list
     */
    protected $subjectSources = [
        '0' => 'lcsh',
        '1' => 'lcshac',
        '2' => 'mesh',
        '3' => 'nal',
        '4' => 'unknown',
        '5' => 'cash',
        '6' => 'rvm'
    ];


    public function getAllSubjectHeadings($extended = false) {
        $returnValue = [];
        $subjectFields = $this->getMarcRecord()->getFields('689');

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

        $returnValue = array_map(
            'unserialize', array_unique(array_map('serialize', $returnValue))
        );

        return $returnValue;
    }
    


    /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @param bool $extended Whether to return a keyed array with the following
     * keys:
     * - heading: the actual subject heading chunks
     * - type: heading type
     * - source: source vocabulary
     *
     * @return array
     */
    public function getAllSubjectHeadingsOLD($extended = false)
    {
        // This is all the collected data:
        $retval = [];

        // Try each MARC field one at a time:
        foreach ($this->subjectFields as $field => $fieldType) {
            // Do we have any results for the current field?  If not, try the next.
            $results = $this->getMarcRecord()->getFields($field);
            if (!$results) {
                continue;
            }

            // If we got here, we found results -- let's loop through them.
            foreach ($results as $result) {
                // Start an array for holding the chunks of the current heading:
                $current = [];

                // Get all the chunks and collect them together:
                $subfields = $result->getSubfields();
                if ($subfields) {
                    foreach ($subfields as $subfield) {
                        // Numeric subfields are for control purposes and should not
                        // be displayed:
                        if (!is_numeric($subfield->getCode())) {
                            $current[] = $subfield->getData();
                        }
                    }
                    // If we found at least one chunk, add a heading to our result:
                    if (!empty($current)) {
                        if ($extended) {
                            $sourceIndicator = $result->getIndicator(2);
                            $source = '';
                            if (isset($this->subjectSources[$sourceIndicator])) {
                                $source = $this->subjectSources[$sourceIndicator];
                            } else {
                                $source = $result->getSubfield('2');
                                if ($source) {
                                    $source = $source->getData();
                                }
                            }
                            $retval[] = [
                                'heading' => $current,
                                'type' => $fieldType,
                                'source' => $source ?: ''
                            ];
                        } else {
                            $retval[] = $current;
                        }
                    }
                }
            }
        }

        // Remove duplicates and then send back everything we collected:
        return array_map(
            'unserialize', array_unique(array_map('serialize', $retval))
        );
    }

}
