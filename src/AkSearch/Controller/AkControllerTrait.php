<?php
/**
 * AK Controller Trait. Contains convenience functions for use in controller classes.
 *
 * PHP version 7
 *
 * Copyright (C) AK Bibliothek Wien 2021.
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

use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * AK Controller Trait. Contains convenience functions for use in controller classes.
 *
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait AkControllerTrait {

    /**
     * Replaces the "translate" method from \VuFind\Controller\AbstractBase. Here it
     * is possible to set a locale. A service locator must be passed to the method.
     *
     * @param string $msg     Message to translate
     * @param array  $tokens  Tokens to inject into the translated string
     * @param string $default Default value to use if no translation is found (null
     * for no default).
     * @param string $locale  The local to use (default is "en")
     * @param ServiceLocatorInterface $sm The service locator
     * 
     * @return string         The translated string
     */
    public function translate($msg, $tokens = [], $default = null, $locale = 'en',
        $sm = null)
    {
        $viewRenderer = $sm->get('ViewRenderer');
        return $viewRenderer->plugin('translate')
            ->__invoke($msg, $tokens, $default, $locale);
    }

    /**
     * Check if the language code specified in $lang is one of the active languages
     * in the [Languages] section of the config.ini file.
     *
     * @param string $lang A language code, e. g. 'en' or 'de'
     * @param ServiceLocatorInterface $sm The service locator
     * 
     * @return boolean true if the language code is valid, false otherwise
     */
    public function isValidLanguage($lang, $sm) {
        $config = $sm->get(\VuFind\Config\PluginManager::class)->get('config')
            ->toArray();
        $langConfig = $config['Languages'] ?? [];
        return key_exists($lang, $langConfig);
    }
}
?>
