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
     * @return Zend\View\Model\ViewModel
     */
    public function contentAction()
    {
        $page = $this->params()->fromRoute('page');

        // AK: Return error page with appropriate message if access is not granted
        if (!$this->isAccessGranted($page)) {
            $errorView = $this->createViewModel()->setTemplate('error/404');
            $errorView->message = 'access_denied';
            $errorView->reason = 'access_denied_content_page';
            return $errorView;
        }

        $themeInfo = $this->serviceLocator->get(\VuFindTheme\ThemeInfo::class);
        $language = $this->serviceLocator->get(\Zend\Mvc\I18n\Translator::class)
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
        return $view;
    }

    /**
     * AK: Check if access is granted to a specific "static content page".
     *
     * @param string   $page The name of the page.
     * 
     * @return boolean true if access is granted, false otherwise
     */
    protected function isAccessGranted($page) {
        // Get config.ini
        $mainConfs = $this->getConfig()->toArray();

        // Get [StaticPagePermissions] section and check if it exists or is empty
        $sppConfs = $mainConfs['StaticPagePermissions'] ?? null ?: null;

        // If no access conditions are set in [StaticPagePermissions], return "true"
        // for "access is granted" as this is the default
        if (!$sppConfs) {
            return true;
        }

        // Get the [StaticPagePermissions] for the current static page
        $sspPageConfs = $sppConfs[$page] ?? null;

        // If no [StaticPagePermissions] exists for the current static page, return
        // "true" for "access is granted" as this is the default
        if (!$sspPageConfs) {
            return true;
        }

        // Get the authorization service
        $auth = $this->getAuthorizationService();

        // Iterate over the permission(s) for the current page
        foreach ($sspPageConfs as $sspPageConf) {
            // If at least one permission results to "true", we return "true" as
            // permission to the current static page is granted.
            if ($auth->isGranted($sspPageConf)) {
                return true;
            }
        }

        // If we got this far we know that there exists one or more config(s) in
        // [StaticPagePermissions] for the current static page but none of them
        // returns "true". In consequence, the current user does not have the
        // permission to see the current static page, so we return "false".
        return false;
    }

}
