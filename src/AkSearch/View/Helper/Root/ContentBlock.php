<?php
/**
 * AK: Extended ContentBlock view helper
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
 * @package  View_Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:view_helpers Wiki
 */
namespace AkSearch\View\Helper\Root;

/**
 * AK: Extending ContentBlock view helper
 *
 * @category AKsearch
 * @package  View_Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:view_helpers Wiki
 */
class ContentBlock extends \VuFind\View\Helper\Root\ContentBlock
{
    /**
     * Render the output of a ContentBlock plugin.
     * 
     * AK: Add active search tab if we have one. Use this to display custom facets
     * for the currently selected search tab.
     *
     * @param \VuFind\ContentBlock\ContentBlockInterface $block The ContentBlock
     * object to render
     *
     * @return string
     */
    public function __invoke($block)
    {
        $template = 'ContentBlock/%s.phtml';
        $className = get_class($block);

        // AK: Add active search tab if current content block is a FacetList. We can
        // then get custom facets for that tab.
        $context = [];
        if (is_a($block, '\VuFind\ContentBlock\FacetList')) {
            $context = $block->getContext($block->activeSearchTab ?? null);
        } else {
            $context = $block->getContext();
        }
        
        return $this->renderClassTemplate($template, $className, $context);
    }
}
