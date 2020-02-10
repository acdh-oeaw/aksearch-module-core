<?php
/**
 * AK: Extended factory for record driver data formatting view helper
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
 * @package  View_Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
namespace AkSearch\View\Helper\Root;

/**
 * AK: Extending factory for record driver data formatting view helper
 *
 * @category AKsearch
 * @package  View_Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
class RecordDataFormatterFactory
    extends \VuFind\View\Helper\Root\RecordDataFormatterFactory
{
    /**
     * Get default specifications for displaying data in collection-info metadata.
     * 
     * AK: Added config "stackCells" for stacking table cells on top of each other
     * if configured. Long table contents need less space that way. Also tweaked the
     * display of the language of the record.
     *
     * @return array
     */
    public function getDefaultCollectionInfoSpecs()
    {
        $spec = new \VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder();

        $spec->setMultiLine(
            'Authors', 'getDeduplicatedAuthors', $this->getAuthorFunction()
        );
        $spec->setLine('Summary', 'getSummary');
        $spec->setLine(
            'Format', 'getFormats', 'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );

        // AK: Commented out default display of the language of the record as this
        // does not translate the language name. Now using more arguments in
        // "setLine" method for translating the language name(s) in the records
        // "core" view (= detail view of a record). See also pull request 413 at
        // VuFind GitHub and there especially the "Files changed" section to get an
        // example of the code used here:
        // https://github.com/vufind-org/vufind/pull/413
        // ORIGINAL: $spec->setLine('Language', 'getLanguages');
        $spec->setLine(
            'Language', 'getLanguages', null,
            ['translate' => true, 'translationTextDomain' => 'Languages::']
        );

        $spec->setTemplateLine(
            'Published', 'getPublicationDetails', 'data-publicationDetails.phtml'
        );
        $spec->setLine(
            'Edition', 'getEdition', null,
            ['prefix' => '<span property="bookEdition">', 'suffix' => '</span>']
        );
        $spec->setTemplateLine('Series', 'getSeries', 'data-series.phtml');

        // AK: Added array with key "stackCells" to "context" array.
        $spec->setTemplateLine(
            'Subjects', 'getAllSubjectHeadings', 'data-allSubjectHeadings.phtml',
            ['context' => ['stackCells' => true]]
        );
        $spec->setTemplateLine('Online Access', true, 'data-onlineAccess.phtml');
        $spec->setTemplateLine(
            'Related Items', 'getAllRecordLinks', 'data-allRecordLinks.phtml'
        );
        $spec->setLine('Notes', 'getGeneralNotes');
        $spec->setLine('Production Credits', 'getProductionCredits');
        $spec->setLine('ISBN', 'getISBNs');
        $spec->setLine('ISSN', 'getISSNs');
        return $spec->getArray();
    }


    /**
     * Get default specifications for displaying data in core metadata.
     *
     * AK: Added config "stackCells" for stacking table cells on top of each other
     *     if configured. Long table contents need less space that way.
     *     Also tweaked the display of the language of the record.
     * 
     * @return array
     */
    public function getDefaultCoreSpecs()
    {
        $spec = new \VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder();
        $spec->setTemplateLine(
            'Published in', 'getContainerTitle', 'data-containerTitle.phtml'
        );
        $spec->setLine(
            'New Title', 'getNewerTitles', null, ['recordLink' => 'title']
        );
        $spec->setLine(
            'Previous Title', 'getPreviousTitles', null, ['recordLink' => 'title']
        );
        /*
        $spec->setMultiLine(
            'Authors', 'getDeduplicatedAuthors', $this->getAuthorFunction()
        );
        */
        $spec->setMultiLine(
            'Authors', 'getAuthorsByRole', null
        );

        $spec->setLine(
            'Format', 'getFormats', 'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );

        // AK: Commented out default display of the language of the record as this
        // does not translate the language name. Now using more arguments in
        // "setLine" method for translating the language name(s) in the records
        // "core" view (= detail view of a record). See also pull request 413 at
        // VuFind GitHub and there especially the "Files changed" section to get an
        // example of the code used here:
        // https://github.com/vufind-org/vufind/pull/413
        // ORIGINAL: $spec->setLine('Language', 'getLanguages');
        $spec->setLine(
            'Language', 'getLanguages', null,
            ['translate' => true, 'translationTextDomain' => 'Languages::']
        );

        $spec->setTemplateLine(
            'Published', 'getPublicationDetails', 'data-publicationDetails.phtml'
        );
        $spec->setLine(
            'Edition', 'getEdition', null,
            ['prefix' => '<span property="bookEdition">', 'suffix' => '</span>']
        );
        $spec->setTemplateLine('Series', 'getSeries', 'data-series.phtml');

        // AK: Added array with key "stackCells" to "context" array.
        $spec->setTemplateLine(
            'Subjects', 'getAllSubjectHeadings', 'data-allSubjectHeadings.phtml',
            ['context' => ['stackCells' => true]]
        );
        $spec->setTemplateLine(
            'child_records', 'getChildRecordCount', 'data-childRecords.phtml',
            ['allowZero' => false]
        );
        $spec->setTemplateLine('Online Access', true, 'data-onlineAccess.phtml');
        $spec->setTemplateLine(
            'Related Items', 'getAllRecordLinks', 'data-allRecordLinks.phtml'
        );
        $spec->setTemplateLine('Tags', true, 'data-tags.phtml');
        return $spec->getArray();
    }


    /**
     * Get the callback function for processing authors.
     *
     * @return Callable
     */
    /*
    protected function getAuthorFunction()
    {
        return function ($data, $options) {
            // Lookup array of singular/plural labels (note that Other is always
            // plural right now due to lack of translation strings).
            $labels = [
                'primary' => ['Main Author', 'Main Authors'],
                'corporate' => ['Corporate Author', 'Corporate Authors'],
                'secondary' => ['Other Authors', 'Other Authors'],
            ];
            // Lookup array of schema labels.
            $schemaLabels = [
                'primary' => 'author',
                'corporate' => 'creator',
                'secondary' => 'contributor',
            ];
            // Lookup array of sort orders.
            $order = ['primary' => 1, 'corporate' => 2, 'secondary' => 3];

            // Sort the data:
            $final = [];
            foreach ($data as $type => $values) {
                $final[] = [
                    'label' => $labels[$type][count($values) == 1 ? 0 : 1],
                    'values' => [$type => $values],
                    'options' => [
                        'pos' => $options['pos'] + $order[$type],
                        'renderType' => 'RecordDriverTemplate',
                        'template' => 'data-authors.phtml',
                        'context' => [
                            'type' => $type,
                            'schemaLabel' => $schemaLabels[$type],
                            'requiredDataFields' => [
                                ['name' => 'role', 'prefix' => 'CreatorRoles::']
                            ],
                        ],
                    ],
                ];
            }
            return $final;
        };
    }
    */
}
