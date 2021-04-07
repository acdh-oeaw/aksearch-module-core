<?php
/**
 * AK: AJAX handler for getting the slide facet template
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
 * @package  AJAX
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ajax_handlers Wiki
 */
namespace AkSearch\AjaxHandler;

use VuFind\Session\Settings as SessionSettings;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;

/**
 * AK: AJAX handler for getting the slide facet template
 *
 * @category AKsearch
 * @package  AJAX
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ajax_handlers Wiki
 */
class GetSlideFacetTemplate extends \VuFind\AjaxHandler\AbstractBase {

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss       Session settings
     * @param RendererInterface $renderer View renderer
     */
    public function __construct(SessionSettings $ss, RendererInterface $renderer) {
        $this->sessionSettings = $ss;
        $this->renderer = $renderer;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params) {
        $this->disableSessionWrites();  // avoid session write timing bug

        $template = $params->fromPost('template');
        $facets = $params->fromPost('facets');

        $html = $this->renderer->render(
            'Recommend/SideFacets/'.$template,
            ['facets' => $facets]
        );       

        // MUST return an array in format [response data, HTTP status code]
        return $this->formatResponse($html);
    }
}

?>