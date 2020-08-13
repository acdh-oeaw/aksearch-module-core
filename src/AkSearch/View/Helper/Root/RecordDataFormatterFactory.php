<?php
/**
 * AK: Extended factory for record driver data formatting view helper
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
     * AK: Added config "stackCells" for stacking table cells on top of each other if
     * configured. Long table contents need less space that way.
     * Added and/or removed and/or tweaked the information that should be displayed
     * in the core record view.
     *
     * @return array
     */
    public function getDefaultCollectionInfoSpecs() {
        $spec = new \VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder();
        
        // AK: Getting authors by role
        $spec->setMultiLine(
            'Authors', 'getContributorsByRole', $this->getAuthorFunction()
        );

        $spec->setLine('Summary', 'getSummary');

        // AK: Removed format
        // TODO: Do we need the format here?
        /*
        $spec->setLine(
            'Format', 'getFormats', 'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );
        */

        // AK: Use custom functions for getting publisher and publication place
        $spec->setLine('PlacePublisher', 'getPulisherPlaceName');

        // AK: Use custom function for getting year of publication. This does get
        // year of publication only if there is no date span available (see below).
        // This is to avoid duplicates.
        $spec->setLine('Year of Publication', 'getPublicationDatesWithoutDateSpan');

        // AK: Get the date span. This is e. g. important for journals or serial
        // publications (e. g. "published from 1960 to 2011"). If we have a date
        // span, no 'Year of Publication' (see above) will be displayed to avoid
        // duplicates.
        $spec->setLine('dateSpan', 'getDateSpan');

        $spec->setLine(
            'Edition', 'getEdition', null,
            ['prefix' => '<span property="bookEdition">', 'suffix' => '</span>']
        );

        // AK: Removed the default display of the language of the record as this
        // does not translate the language name. Now using more arguments in
        // "setLine" method for translating the language name(s) in the records
        // "core" view (= detail view of a record). See also pull request 413 at
        // VuFind GitHub and there especially the "Files changed" section to get an
        // example of the code used here:
        // https://github.com/vufind-org/vufind/pull/413
        $spec->setLine(
            'Language', 'getLanguages', null,
            ['translate' => true, 'translationTextDomain' => 'Languages::']
        );

        $spec->setTemplateLine('Series', 'getSeries', 'data-series.phtml');

        // AK: Added array with key "stackCells" to "context" array. Using the new
        // option "stackCells" allows for saving some display space.
        $spec->setTemplateLine(
            'Subjects', 'getAllSubjectHeadings', 'data-allSubjectHeadings.phtml',
            ['context' => ['stackCells' => true]]
        );

        $spec->setTemplateLine('Online Access', true, 'data-onlineAccess.phtml');
        $spec->setTemplateLine(
            'Related Items', 'getAllRecordLinks', 'data-allRecordLinks.phtml'
        );

        // AK: Added physical description (Marc21 field 300)
        $spec->setTemplateLine('Physical Description', 'getPhysicalDescriptions',
            'data-bulletList.phtml');

        // AK: Using template line instead of a default line
        $spec->setTemplateLine('Notes', 'getGeneralNotes', 'data-bulletList.phtml');

        $spec->setLine('Production Credits', 'getProductionCredits');
        $spec->setLine('ISBN', 'getISBNs');
        $spec->setLine('ISSN', 'getISSNs');

        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * AK: Added config "stackCells" for stacking table cells on top of each other if
     * configured. Long table contents need less space that way.
     * Added and/or removed and/or tweaked the information that should be displayed
     * in the core record view.
     * 
     * @return array
     */
    public function getDefaultCoreSpecs() {
        $spec = new \VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder();

        // AK: Getting parent records with direct link to the parent records detail
        // page.
        $spec->setTemplateLine(
            'Published in', 'getConsolidatedParents', 'data-containerTitle.phtml'
        );

        // AK: Getting authors by role
        $spec->setMultiLine(
            'Authors', 'getContributorsByRole', $this->getAuthorFunction()
        );
        
        // AK: Removed format
        // TODO: Do we need the format here?
        /*
        $spec->setLine(
            'Format', 'getFormats', 'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );
        */

        // AK: Use custom functions for getting publisher and publication place
        $spec->setLine('PlacePublisher', 'getPulisherPlaceName');

        // AK: Use custom function for getting year of publication. This does get
        // year of publication only if there is no date span available (see below).
        // This is to avoid duplicates.
        $spec->setLine('Year of Publication', 'getPublicationDatesWithoutDateSpan');

        // AK: Get the date span. This is e. g. important for journals or serial
        // publications (e. g. "published from 1960 to 2011"). If we have a date
        // span, no 'Year of Publication' (see above) will be displayed to avoid
        // duplicates.
        $spec->setLine('dateSpan', 'getDateSpan');

        $spec->setLine(
            'Edition', 'getEdition', null,
            ['prefix' => '<span property="bookEdition">', 'suffix' => '</span>']
        );

        // AK: Removed the default display of the language of the record as this
        // does not translate the language name. Now using more arguments in
        // "setLine" method for translating the language name(s) in the records
        // "core" view (= detail view of a record). See also pull request 413 at
        // VuFind GitHub and there especially the "Files changed" section to get an
        // example of the code used here:
        // https://github.com/vufind-org/vufind/pull/413
        $spec->setLine(
            'Language', 'getLanguages', null,
            ['translate' => true, 'translationTextDomain' => 'Languages::']
        );

        // AK: Get preceding titles
        $spec->setTemplateLine(
            'Precedings', 'getPrecedings', 'data-relations.phtml'
        );

        // AK: Get succeeding titles
        $spec->setTemplateLine(
            'Succeedings', 'getSucceedings', 'data-relations.phtml'
        );

        // AK: Get other editions
        $spec->setTemplateLine(
            'OtherEditions', 'getOtherEditions', 'data-relations.phtml'
        );

        // AK: Get other physical forms
        $spec->setTemplateLine(
            'OtherPhysForms', 'getOtherPhysForms', 'data-relations.phtml'
        );

        // AK: Get "issued with" information
        $spec->setTemplateLine(
            'IssuedWith', 'getIssuedWith', 'data-relations.phtml'
        );

        // AK: Get other relations
        $spec->setTemplateLine(
            'OtherRelations', 'getOtherRelations', 'data-relations.phtml'
        );

        $spec->setTemplateLine('Series', 'getSeries', 'data-series.phtml');

        // AK: Added array with key "stackCells" to "context" array. Using the new
        // option "stackCells" allows for saving some display space.
        $spec->setTemplateLine(
            'Subjects', 'getAllSubjectHeadings', 'data-allSubjectHeadings.phtml',
            ['context' => ['stackCells' => true]]
        );

        // AK: Get dewey classification
        $spec->setTemplateLine('Classification', 'getAllDeweys', 'data-localDewey');

        $spec->setTemplateLine(
            'child_records', 'getChildRecordCount', 'data-childRecords.phtml',
            ['allowZero' => false]
        );
        $spec->setTemplateLine('Online Access', true, 'data-onlineAccess.phtml');
        $spec->setTemplateLine(
            'Related Items', 'getAllRecordLinks', 'data-allRecordLinks.phtml'
        );

        // AK: Added physical description (Marc21 field 300)
        $spec->setTemplateLine('Physical Description', 'getPhysicalDescriptions',
            'data-bulletList.phtml');

        // AK: Added notes (Marc21 field 500)
        $spec->setTemplateLine('Notes', 'getGeneralNotes', 'data-bulletList.phtml');

        
        $spec->setTemplateLine('Tags', true, 'data-tags.phtml');
        
        return $spec->getArray();
    }

    /**
     * AK: Get a callback function for getting authors/contributors.
     * See https://vufind.org/wiki/development:architecture:record_data_formatter
     *
     * @return Callable
     */
    protected function getAuthorFunction() {
        // The function to return
        return function ($data, $options) {
            // Initialize the result array
            $result = [];
            // Initialize a counter. This is used for the display position of the
            // author roles. We use a simple counter as the roles and author values
            // should be displayed in the order we get them from the RecordDriver
            // function getContributorsByRole().
            $order = 0;

            foreach ($data as $role => $entries) {
                
                $values = [];
                foreach ($entries as $entry) {
                    $values[] = [$entry['auth'] => $entry['name']];
                }

                if (!empty($values)) {
                    $order++;
                    $result[] = [
                        'label' => 'CreatorRoles::'.$role,
                        'values' => $values,
                        'options' => [
                            // The position/order where this entry should be displayed.
                            'pos' => $options['pos'] + $order,
                            // Indicates that we want to use a .phtml template
                            'renderType' => 'RecordDriverTemplate',
                            // The .phtml template to use
                            'template' => 'data-authors.phtml',
                            // Values that are passed to the template
                            // TODO: Should we add some schema.org stuff here?
                            'context' => [
                                'role' => $role
                            ]
                        ]
                    ];
                }
            }

            return $result;
        };
    }

    /**
     * Get default specifications for displaying data in the description tab.
     * AK: Added/removed some information.
     *
     * @return array
     */
    public function getDefaultDescriptionSpecs()
    {
        $spec = new \VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder();
        $spec->setLine('TitleAlt', 'getTitleAlt');
        $spec->setTemplateLine('Summary', true, 'data-summary.phtml');
        // AK: Moved notes to core details
        //$spec->setTemplateLine('Notes', 'getGeneralNotes', 'data-bulletList.phtml');
        // AK: Moved physical description to core details
        //$spec->setTemplateLine('Physical Description', 'getPhysicalDescriptions',
        //    'data-bulletList.phtml');
        $spec->setLine('ParticipantPerformerNote', 'getParticipantPerformerNotes');
        $spec->setLine('Publication Frequency', 'getPublicationFrequency');
        $spec->setLine('Playing Time', 'getPlayingTimes');
        $spec->setLine('Format', 'getSystemDetails');
        $spec->setLine('Audience', 'getTargetAudienceNotes');
        $spec->setLine('Awards', 'getAwards');
        $spec->setLine('Production Credits', 'getProductionCredits');
        $spec->setLine('Bibliography', 'getBibliographyNotes');
        $spec->setLine('ISBN', 'getISBNs');
        $spec->setLine('ISSN', 'getISSNs');
        $spec->setLine('DOI', 'getCleanDOI');
        $spec->setLine('Related Items', 'getRelationshipNotes');
        $spec->setLine('Access', 'getAccessRestrictions');
        $spec->setLine('Finding Aid', 'getFindingAids');
        $spec->setLine('Publication_Place', 'getHierarchicalPlaceNames');
        $spec->setLine('Level', 'getBibliographicLevel', null,
            ['translate' => true]);
        $spec->setLine('Form', 'getForms', null, ['translate' => true]);
        $spec->setLine('Content', 'getContents', null, ['translate' => true]);
        $spec->setLine('MediaTypes', 'getMediaTypes', null, ['translate' => true]);
        $spec->setLine('Carrier', 'getCarriers', null, ['translate' => true]);
        $spec->setLine('Supplements', 'getSupplements');
        $spec->setLine('SupplementParents', 'getSupplementParents');
        $spec->setTemplateLine('Author Notes', true, 'data-authorNotes.phtml');
        return $spec->getArray();
    }

}
