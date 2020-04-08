<?php
/**
 * AK: Extended factory to build UrlQueryHelper.
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
 * @package  Search
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki Wiki
 */
namespace AkSearch\Search\Factory;

use VuFind\Search\Base\Params;
use AkSearch\Search\UrlQueryHelper;

/**
 * AK: Extending factory to build UrlQueryHelper.
 *
 * @category AKsearch
 * @package  Search
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki Wiki
 */
class UrlQueryHelperFactory extends \VuFind\Search\Factory\UrlQueryHelperFactory
{
    /**
     * Construct the UrlQueryHelper
     * 
     * AK: Use custom UrlQueryHelper
     *
     * @param Params $params VuFind search parameters
     * @param array  $config Config options
     *
     * @return UrlQueryHelper
     */
    public function fromParams(Params $params, array $config = [])
    {
        $finalConfig = $this->addDefaultsToConfig($params, $config);
        // AK: Use custom UrlQueryHelper
        return new UrlQueryHelper(
            $this->getUrlParams($params, $finalConfig),
            $params->getQuery(),
            $finalConfig
        );
    }
    
}
