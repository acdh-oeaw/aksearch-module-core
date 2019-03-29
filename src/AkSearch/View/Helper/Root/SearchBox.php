<?php
/**
 * AK: Extended search box view helper
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

    /**
     * AK: Class variable for authorization service
     *
     * @var \ZfcRbac\Service\AuthorizationService
     */
    protected $authService;

    /**
     * AK: Permissions config for search handlers
     *
     * @var array
     */
    protected $permissionsConfig;

    /**
     * Constructor
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

        // AK: Creating authorization service for checking permissions from permissions.ini
        $this->authService = $container->get('ZfcRbac\Service\AuthorizationService');
        if (!$this->authService) {
            throw new \Exception('Authorization service missing');
        }

        // AK: Get [Permissions] configs from searchbox.ini
        $this->permissionsConfig = $config['Permissions'];
    }

    /**
     * Support method for getHandlers() -- load basic settings.
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
            if ($this->getPermission($searchVal)) {
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
                    if ($this->getPermission($searchVal)) {
                        $handlers[] = [
                            'value' => $type . ':' . $target . '|' . $searchVal,
                            'label' => $j == 1 ? $label : $searchDesc,
                            'indent' => $j == 1 ? false : true,
                            'selected' => $selected
                        ];
                    }
                }

                // Should we add alphabrowse links?
                if ($target === 'Solr' && $this->alphaBrowseOptionsEnabled()) {
                    $addedBrowseHandlers = true;
                    $handlers = array_merge(
                        $handlers,
                        $this->getAlphaBrowseHandlers($activeHandler)
                    );
                }
            } elseif ($type == 'External') {
                // AK: Check permissions using external search target
                if ($this->getPermission($target)) {
                    $handlers[] = [
                        'value' => $type . ':' . $target, 'label' => $label,
                        'indent' => false, 'selected' => false
                    ];
                }
            }
        }

        // If we didn't add alphabrowse links above as part of the Solr section
        // but we are configured to include them, we should add them now:
        if (!$addedBrowseHandlers && $this->alphaBrowseOptionsEnabled()) {
            $handlers = array_merge(
                $handlers,
                $this->getAlphaBrowseHandlers($activeHandler, false)
            );
        }

        // If we didn't find an exact match for a selected index, use a fuzzy
        // match:
        if (!$selectedFound && $backupSelectedIndex !== false) {
            $handlers[$backupSelectedIndex]['selected'] = true;
        }

        return $handlers;
    }

    /**
     * AK: Check permissions for the given search value
     *
     * @param string   $searchVal The name of the search value
     * @return boolean            True if permission is granted, false otherwise
     */
    protected function getPermission($searchVal)
    {
        $permissionsToCheck = [];
        foreach ($this->permissionsConfig as $permissionName => $permissionHanlderArray) {
            if (in_array($searchVal, $permissionHanlderArray)) {
                $permissionsToCheck[] = $permissionName;
            }
        }

        if (empty($permissionsToCheck)) {
            // Return true if no permission configs are set for a search value.
            return true;
        } else {
            // If permission configs are set for a search value, check the permission status.
            foreach ($permissionsToCheck as $permissionToCheck) {
                if ($this->authService->isGranted($permissionToCheck)) {
                    // Return true if permission is granted.
                    return true;
                }
            }

            // Default
            return false;
        }

        // Fallback
        return true;
    }






    

    /**
     * Support method for getCombinedHandlers() -- retrieve/validate configuration.
     *
     * @param string $activeSearchClass Active search class ID
     *
     * @return array
     */
    protected function getCombinedHandlerConfig($activeSearchClass)
    {
        if (!isset($this->cachedConfigs[$activeSearchClass])) {
            // Load and validate configuration:
            $settings = isset($this->config['CombinedHandlers'])
                ? $this->config['CombinedHandlers'] : [];
            if (empty($settings)) {
                throw new \Exception('CombinedHandlers configuration missing.');
            }

            $typeCount = count($settings['type']);
            if ($typeCount != count($settings['target'])
                || $typeCount != count($settings['label'])
            ) {
                throw new \Exception('CombinedHandlers configuration incomplete.');
            }

            // Add configuration for the current search class if it is not already
            // present:
            if (!in_array($activeSearchClass, $settings['target'])) {
                $settings['type'][] = 'VuFind';
                $settings['target'][] = $activeSearchClass;
                $settings['label'][] = $activeSearchClass;
            }
            
            $this->cachedConfigs[$activeSearchClass] = $settings;
        }

        return $this->cachedConfigs[$activeSearchClass];
    }
}
