<?php
/**
 * AK: Trait to add facet mincount setting to a Params object.
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
 * @package  Search
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace AkSearch\Search\Params;

use Laminas\Config\Config;

/**
 * AK: Trait to add facet mincount setting to a Params object.
 *
 * @category AKsearch
 * @package  Search
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait FacetMincountTrait
{
    /**
     * AK: mincount option for facets. Default is 1.
     *
     * @var int
     */
    protected $facetMincount = 1;

    /**
     * AK: Per-field facet result mincount
     *
     * @var array
     */
    protected $facetMincountByField = [];

    /**
     * AK: Initialize facet mincount from a Config object.
     *
     * @param Config $config Configuration
     *
     * @return void
     */
    protected function initFacetMincountFromConfig(Config $config = null)
    {
        if (is_numeric($config->facet_mincount ?? null)) {
            $this->setFacetmincount($config->facet_mincount);
        }
        foreach ($config->facet_mincount_by_field ?? [] as $k => $v) {
            $this->facetMincountByField[$k] = $v;
        }
    }

    /**
     * AK: Set facet mincount
     *
     * @param int $m the mincount value. Default is 1.
     *
     * @return void
     */
    public function setFacetmincount($m)
    {
        $this->facetMincount = $m;
    }

    /**
     * AK: Get facet mincount
     *
     * @return int
     */
    public function getFacetMincount() {
        return $this->facetMincount;
    }

    /**
     * AK: Set facet mincount by Field
     *
     * @param array $new Associative array of $field name => $mincount
     *
     * @return void
     */
    public function setFacetMincountByField(array $new)
    {
        $this->facetMincountByField = $new;
    }

    /**
     * AK: Get the facet mincount for the specified field.
     * 
     * TODO: Check if it makes sense to use mincount for hierarchical facets
     *
     * @param string $field Field to look up
     *
     * @return int
     */
    protected function getFacetMincountForField($field)
    {
        return $this->facetMincountByField[$field] ?? $this->facetMincount;
    }
}
?>