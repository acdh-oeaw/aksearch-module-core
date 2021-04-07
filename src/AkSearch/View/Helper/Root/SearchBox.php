<?php
/**
 * AK: Extended search box view helper
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
 * @package  View_Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:view_helpers Wiki
 */
namespace AkSearch\View\Helper\Root;

use VuFind\Search\Options\PluginManager as OptionsManager;
use Interop\Container\ContainerInterface;

/**
 * AK: Extending search box view helper
 *
 * @category AKsearch
 * @package  View_Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:view_helpers Wiki
 */
class SearchBox extends \VuFind\View\Helper\Root\SearchBox
{

    use \AkSearch\Role\PermissionTrait;

    /**
     * AK: Class variable for authorization service
     *
     * @var \LmcRbacMvc\Service\AuthorizationService
     */
    protected $authService;

    /**
     * AK: Permissions config for search handlers
     *
     * @var array
     */
    protected $permissionsConfig;

    /**
     * AK: Constructor
     *
     * @param OptionsManager     $optionsManager    Search options plugin manager
     * @param array              $config            Configuration for search box
     * @param array              $placeholders      Array of placeholders keyed by
     * backend
     * @param array              $alphabrowseConfig source => label config for
     * alphabrowse options to display in combined box (empty for none)
     * @param ContainerInterface $container         Container object from factory
     */
    public function __construct(
        OptionsManager $optionsManager,
        $config = [],
        $placeholders = [],
        $alphabrowseConfig = [],
        ContainerInterface $container
    ) {
        parent::__construct($optionsManager, $config, $placeholders, $alphabrowseConfig);

        // AK: Creating authorization service for checking permissions from
        //     permissions.ini
        //     TODO: Get only \LmcRbacMvc\Service\AuthorizationService passed from
        //           SearchBoxFactory instead of whole container object!
        $this->authService = $container
            ->get(\LmcRbacMvc\Service\AuthorizationService::class);
        if (!$this->authService) {
            throw new \Exception('Authorization service missing');
        }

        // AK: Get [Permissions] configs from searchbox.ini
        $this->permissionsConfig = $config['Permissions'];
    }

    /**
     * Support method for getHandlers() -- load basic settings.
     * AK: Checking for permissions for using the search handler.
     *
     * @param string $activeSearchClass Active search class ID
     * @param string $activeHandler     Active search handler
     *
     * @return array
     */
    protected function getBasicHandlers($activeSearchClass, $activeHandler)
    {
        $handlers = [];
        $options = $this->optionsManager->get($activeSearchClass);
        foreach ($options->getBasicHandlers() as $searchVal => $searchDesc) {
            // AK: Check permissions
            if ($this->getPermission($this->authService, $this->permissionsConfig, $searchVal)) {
                $handlers[] = [
                    'value' => $searchVal, 'label' => $searchDesc, 'indent' => false,
                    'selected' => ($activeHandler == $searchVal)
                ];
            }
        }
        return $handlers;
    }

    /**
     * Support method for getHandlers() -- load combined settings.
     * AK: Checking for permissions for using the search handler.
     *
     * @param string $activeSearchClass Active search class ID
     * @param string $activeHandler     Active search handler
     *
     * @return array
     */
    protected function getCombinedHandlers($activeSearchClass, $activeHandler)
    {
        // Build settings:
        $handlers = [];
        $selectedFound = false;
        $backupSelectedIndex = false;
        $addedBrowseHandlers = false;
        $settings = $this->getCombinedHandlerConfig($activeSearchClass);
        $typeCount = count($settings['type']);
        for ($i = 0; $i < $typeCount; $i++) {
            $type = $settings['type'][$i];
            $target = $settings['target'][$i];
            $label = $settings['label'][$i];

            if ($type == 'VuFind') {
                $options = $this->optionsManager->get($target);
                $j = 0;
                $basic = $options->getBasicHandlers();
                if (empty($basic)) {
                    $basic = ['' => ''];
                }
                foreach ($basic as $searchVal => $searchDesc) {
                    $j++;
                    $selected = $target == $activeSearchClass
                        && $activeHandler == $searchVal;
                    if ($selected) {
                        $selectedFound = true;
                    } elseif ($backupSelectedIndex === false
                        && $target == $activeSearchClass
                    ) {
                        $backupSelectedIndex = count($handlers);
                    }

                    // AK: Check permissions
                    if ($this->getPermission($this->authService, $this->permissionsConfig, $searchVal)) {
                        
                        // Depending on whether or not the current section has a label,
                        // we'll either want to override the first label and indent
                        // subsequent ones, or else use all default labels without
                        // any indentation.
                        if (empty($label)) {
                            $finalLabel = $searchDesc;
                            $indent = false;
                        } else {
                            $finalLabel = $j == 1 ? $label : $searchDesc;
                            $indent = $j == 1 ? false : true;
                        }
                        $handlers[] = [
                            'value' => $type . ':' . $target . '|' . $searchVal,
                            'label' => $finalLabel,
                            'indent' => $indent,
                            'selected' => $selected,
                            'group' => $settings['group'][$i],
                        ];
                    }
                }

                // Should we add alphabrowse links?
                if ($target === 'Solr' && $this->alphaBrowseOptionsEnabled()) {
                    $addedBrowseHandlers = true;
                    $handlers = array_merge(
                        $handlers,
                        // Only indent alphabrowse handlers if label is non-empty:
                        $this->getAlphaBrowseHandlers($activeHandler, !empty($label))
                    );
                }
            } elseif ($type == 'External') {
                // AK: Check permissions using external search target
                if ($this->getPermission($this->authService, $this->permissionsConfig, $target)) {
                    $handlers[] = [
                        'value' => $type . ':' . $target, 'label' => $label,
                        'indent' => false, 'selected' => false,
                        'group' => $settings['group'][$i],
                    ];
                }
            }
        }

        // If we didn't add alphabrowse links above as part of the Solr section
        // but we are configured to include them, we should add them now:
        if (!$addedBrowseHandlers && $this->alphaBrowseOptionsEnabled()) {
            $handlers = array_merge(
                $handlers, $this->getAlphaBrowseHandlers($activeHandler, false)
            );
        }

        // If we didn't find an exact match for a selected index, use a fuzzy
        // match:
        if (!$selectedFound && $backupSelectedIndex !== false) {
            $handlers[$backupSelectedIndex]['selected'] = true;
        }
        return $handlers;
    }

}
