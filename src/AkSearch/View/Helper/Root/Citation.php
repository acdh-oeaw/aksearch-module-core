<?php
/**
 * Citation view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace AkSearch\View\Helper\Root;

use VuFind\Date\DateException;

/**
 * Citation view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Citation extends \VuFind\View\Helper\Root\Citation
{

    /**
     * Store a record driver object and return this object so that the appropriate
     * template can be rendered.
     * 
     * AK: Fix some of the values for better citation results.
     *
     * @param \VuFind\RecordDriver\Base $driver Record driver object.
     *
     * @return Citation
     */
    public function __invoke($driver)
    {
        // Build author list:
        $authors = [];

        // AK: Don't use date subfield from author MARC field as this results in an
        //     erroneous output.
        $primary = $driver->tryMethod('getPrimaryAuthorsWithoutDate');
        if (empty($primary)) {
            $primary = $driver->tryMethod('getPrimaryCorporateAuthorsWithoutDate');
        }
        if (!empty($primary)) {
            // AK: Removed [] as this produces a multidimentional array. When using
            //     the multidimentional array in "array_unique" function below, the
            //     PHP notice "Array to string conversion" is thrown.
            //     TODO: Create pull request in master code!
            $authors = $primary;
        }
        $secondary = $driver->tryMethod('getSecondaryAuthorsWithoutDate');
        // AK: Use corporate author if no default secondary author was found
        if (empty($secondary)) {
            $secondary = $driver->tryMethod(
                'getSecondaryCorporateAuthorsWithoutDate'
            );
        }
        if (is_array($secondary) && !empty($secondary)) {
            $authors = array_unique(array_merge($authors, $secondary));
        }

        // Get best available title details:
        $title = $driver->tryMethod('getShortTitle');
        $subtitle = $driver->tryMethod('getSubtitle');
        if (empty($title)) {
            $title = $driver->tryMethod('getTitle');
        }
        if (empty($title)) {
            $title = $driver->getBreadcrumb();
        }
        // Find subtitle in title if they're not separated:
        if (empty($subtitle) && strstr($title, ':')) {
            list($title, $subtitle) = explode(':', $title, 2);
        }

        // Extract the additional details from the record driver:
        $publishers = $driver->tryMethod('getPublishers');
        $pubDates = $driver->tryMethod('getPublicationDates');
        $pubPlaces = $driver->tryMethod('getPlacesOfPublication');
        $edition = $driver->tryMethod('getEdition');

        // Store everything:
        // AK: Add titleSection
        $this->driver = $driver;
        $this->details = [
            'authors' => $this->prepareAuthors($authors),
            'title' => trim($title), 'subtitle' => trim($subtitle),
            'titleSection' => $driver->tryMethod('getTitleSection'),
            'pubPlace' => $pubPlaces[0] ?? null,
            'pubName' => $publishers[0] ?? null,
            'pubDate' => $pubDates[0] ?? null,
            'edition' => empty($edition) ? [] : [$edition],
            'journal' => $driver->tryMethod('getWholeContainerTitle')
        ];

        return $this;
    }

    /**
     * Get the full title for an APA citation.
     * 
     * AK: Add title section. Add spaces before colon.
     *     Info: MLA and Chicago are also using this function for getting the title.
     *
     * @return string
     */
    protected function getAPATitle()
    {
        // Create Title
        $title = $this->stripPunctuation($this->details['title']);
        if (isset($this->details['subtitle'])) {
            $subtitle = $this->stripPunctuation($this->details['subtitle']);
            // Capitalize subtitle and apply it, assuming it really exists:
            if (!empty($subtitle)) {
                $subtitle
                    = strtoupper(substr($subtitle, 0, 1)) . substr($subtitle, 1);
                $title .= ' : ' . $subtitle;
            }
        }
        if (isset($this->details['titleSection'])) {
            $titleSection = $this->stripPunctuation($this->details['titleSection']);
            // Capitalize title section and apply it, assuming it really exists:
            if (!empty($titleSection)) {
                $titleSection
                    = strtoupper(substr($titleSection, 0, 1)) . substr($titleSection, 1);
                $title .= ' : ' . $titleSection;
            }
        }

        return $title;
    }

    /**
     * Construct page range portion of citation.
     * 
     * AK: First try to get ready-to-use page range from datafield VAR, subfield p.
     *     If that fails, try the default way of getting it.
     *
     * @return string
     */
    protected function getPageRange()
    {
        return $this->driver->tryMethod('getContainerPageRange')
            ?? parent::getPageRange();
    }

}
