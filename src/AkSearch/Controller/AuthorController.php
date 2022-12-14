<?php
/**
 * AK: Extended Author Search Controller
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
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace AkSearch\Controller;

/**
 * AK: Extending Author Search Options
 *
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AuthorController extends \VuFind\Controller\AuthorController
{
    /**
     * Sets the configuration for displaying author results
     * 
     * AK: Add record banner config to view
     *
     * @return mixed
     */
    public function resultsAction()
    {
        // Get the view from the parent controller
        $view = parent::resultsAction();

        // Add the record banner config
        $view->recordBannerConfig = isset($this->getConfig()->RecordBanner)
            ? $this->getConfig()->RecordBanner->toArray()
            : null;

        // Return the view with the record banner config
        return $view;
    }

    
}
