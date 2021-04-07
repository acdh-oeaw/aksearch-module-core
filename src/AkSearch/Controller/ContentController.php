<?php
/**
 * AK: Extended Content Controller
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
 * AK: Extending Content Controller
 *     Adding possibility to view content based on a permission in permissions.ini
 *
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class ContentController extends \VuFind\Controller\ContentController
{
    /**
     * Default action if none provided
     * 
     * AK: Additional functionalities:
     *  - Added access restrictions via permissions.ini
     *  - Added possibility to pass variables to content page from config.ini
     *
     * @return Laminas\View\Model\ViewModel
     */
    public function contentAction()
    {
        $page = $this->params()->fromRoute('page');

        // AK: Get config.ini
        $mainConfs = $this->getConfig()->toArray();
        
        // AK: Return error page with appropriate message if access is not granted
        if (!$this->isAccessGranted($mainConfs, $page)) {
            $errorView = $this->createViewModel()->setTemplate('error/404');
            $errorView->message = 'access_denied';
            $errorView->reason = 'access_denied_content_page';
            return $errorView;
        }

        $themeInfo = $this->serviceLocator->get(\VuFindTheme\ThemeInfo::class);
        $language = $this->serviceLocator->get(\Laminas\Mvc\I18n\Translator::class)
            ->getLocale();
        $defaultLanguage = $this->getConfig()->Site->language;

        // Try to find a template using
        // 1.) Current language suffix
        // 2.) Default language suffix
        // 3.) No language suffix
        $currentTpl = "templates/content/{$page}_$language.phtml";
        $defaultTpl = "templates/content/{$page}_$defaultLanguage.phtml";
        if (null !== $themeInfo->findContainingTheme($currentTpl)) {
            $page = "{$page}_$language";
        } elseif (null !== $themeInfo->findContainingTheme($defaultTpl)) {
            $page = "{$page}_$defaultLanguage";
        }

        if (empty($page) || 'content' === $page
            || null === $themeInfo->findContainingTheme(
                "templates/content/$page.phtml"
            )
        ) {
            return $this->notFoundAction($this->getResponse());
        }

        $view = $this->createViewModel(['page' => $page]);

        // AK: Get variables for the current static content page
        $vars = $this->getVariables($mainConfs, $page);
        if ($vars) {
            // Set variables if we found some
            $view->setVariables($vars);
        }
        
        return $view;
    }

    /**
     * AK: Check if access is granted to a specific "static content page".
     *
     * @param array    $mainConfs The main configs form config.ini as an array
     * @param string   $page      The name of the static content page
     * 
     * @return boolean true if access is granted, false otherwise
     */
    protected function isAccessGranted($mainConfs, $page) {
        // Get [StaticPagePermissions] section and check if it exists or is empty
        $sppConfs = $mainConfs['StaticPagePermissions'] ?? null ?: null;

        // If no access conditions are set in [StaticPagePermissions], return "true"
        // for "access is granted" as this is the default
        if (!$sppConfs) {
            return true;
        }

        // Get the [StaticPagePermissions] for the current static page
        $sppPageConfs = $sppConfs[$page] ?? null;

        // If no [StaticPagePermissions] exists for the current static page, return
        // "true" for "access is granted" as this is the default
        if (!$sppPageConfs) {
            return true;
        }

        // Get the authorization service
        $auth = $this->getAuthorizationService();

        // Iterate over the permission(s) for the current page
        foreach ($sppPageConfs as $sppPageConf) {
            // If at least one permission results to "true", we return "true" as
            // permission to the current static page is granted.
            if ($auth->isGranted($sppPageConf)) {
                return true;
            }
        }

        // If we got this far we know that there exists one or more config(s) in
        // [StaticPagePermissions] for the current static page but none of them
        // returns "true". In consequence, the current user does not have the
        // permission to see the current static page, so we return "false".
        return false;
    }

    /**
     * Get an array of variables from config.ini in section [StaticPageVariables].
     *
     * @param array  $mainConfs The main configs form config.ini as an array
     * @param string $page      The name of the static content page
     * 
     * @return array An array with variables for the current static content page or
     *               an empty array if no variables are set
     */
    protected function getVariables($mainConfs, $page) {
        // Get [StaticPageVariables] section and check if it exists or is empty
        $spvConfs = $mainConfs['StaticPageVariables'] ?? null ?: null;

        // If no variables are set in [StaticPageVariables], return an empty array
        if (!$spvConfs) {
            return [];
        }

        // Get the [StaticPageVariables] for the current static page
        $spvPageConfs = $spvConfs[$page] ?? null;

        // If no [StaticPageVariables] exists for the current static page, return an
        // empty array
        if (!$spvPageConfs) {
            return [];
        }

        // When we came this far, some variables are set for the current static
        // content page, so we return them.
        return $spvPageConfs;
    }

}
