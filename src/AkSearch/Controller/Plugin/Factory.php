<?php
/**
 * AK: Extended factory for controller plugins.
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
 * @package  Controller_Plugins
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace AkSearch\Controller\Plugin;

use Zend\ServiceManager\ServiceManager;

/**
 * AK: Extending factory for controller plugins.
 *
 * @category AKsearch
 * @package  Controller_Plugins
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 *
 * @codeCoverageIgnore
 */
class Factory extends \VuFind\Controller\Plugin\Factory
{
    
    /**
     * Construct the NewItems plugin.
     * AK: Use the NewItems plugin from the AkSearch module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return NewItems
     */
    public static function getNewItems(ServiceManager $sm)
    {
        $search = $sm->get('VuFind\Config\PluginManager')->get('searches');
        $config = isset($search->NewItem)
            ? $search->NewItem : new \Zend\Config\Config([]);
        $siteConfig = $sm->get('VuFind\Config\PluginManager')->get('config')->Site;

        return new NewItems($config, $siteConfig);
    }

}
