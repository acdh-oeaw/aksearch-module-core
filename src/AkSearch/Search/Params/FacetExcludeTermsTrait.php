<?php
/**
 * AK: Trait to add facet excludeTerms setting to a Params object.
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
 * AK: Trait to add facet excludeTerms setting to a Params object.
 *
 * @category AKsearch
 * @package  Search
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait FacetExcludeTermsTrait
{
    /**
     * AK: excludeTerms option for facets. Default is empty string.
     *
     * @var string
     */
    protected $facetExcludeTerms = "";

    /**
     * AK: Per-field facet excludeTerms
     *
     * @var array
     */
    protected $facetExcludeTermsByField = [];

    /**
     * AK: Initialize facet excludeTerms from a Config object.
     *
     * @param Config $config Configuration
     *
     * @return void
     */
    protected function initFacetExcludeTermsFromConfig(Config $config = null)
    {
        if ($config->facet_excludeterms ?? null ?: null) {
            $this->setFacetExcludeTerms($config->facet_excludeterms);
        }
        foreach ($config->facet_excludeterms_by_field ?? [] as $k => $v) {
            $this->facetExcludeTermsByField[$k] = $v;
        }
    }

    /**
     * AK: Set facet excludeTerms
     *
     * @param string $e the excludeTerms value. Default is empty string.
     *
     * @return void
     */
    public function setFacetExcludeTerms($e)
    {
        $this->facetExcludeTerms = $e;
    }

    /**
     * AK: Get facet excludeTerms
     *
     * @return string
     */
    public function getFacetExcludeTerms() {
        return $this->facetExcludeTerms;
    }

    /**
     * AK: Set facet excludeTerms by Field
     *
     * @param array $new Associative array of $field name => $excludeTerms
     *
     * @return void
     */
    public function setFacetExcludeTermsByField(array $new)
    {
        $this->facetExcludeTermsByField = $new;
    }

    /**
     * AK: Get the facet excludeTerms for the specified field.
     * 
     * TODO: Check if it makes sense to use excludeTerms for hierarchical facets
     *
     * @param string $field Field to look up
     *
     * @return string
     */
    protected function getFacetExcludeTermsForField($field)
    {
        return $this->facetExcludeTermsByField[$field] ?? $this->facetExcludeTerms;
    }
}
?>