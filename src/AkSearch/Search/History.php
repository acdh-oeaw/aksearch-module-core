<?php
/**
 * AK: Extended VuFind Search History Helper
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
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace AkSearch\Search;

/**
 * AK: Extending VuFind Search History Helper
 *
 * @category AKsearch
 * @package  Search
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class History extends \VuFind\Search\History
{

    /**
     * AK: Class variable for authorization service
     *
     * @var \ZfcRbac\Service\AuthorizationService
     */
    protected $authService;

    /**
     * History constructor
     * AK: Get authorization service from HistoryFactory.
     *
     * @param \VuFind\Db\Table\Search               $searchTable    Search table
     * @param string                                $sessionId      Session ID
     * @param \VuFind\Search\Results\PluginManager  $resultsManager Results manager
     * @param \Zend\Config\Config                   $config         Configuration
     * @param \ZfcRbac\Service\AuthorizationService $authService    Authorization
     * service for checking permissions
     */
    public function __construct($searchTable, $sessionId, $resultsManager,
        \Zend\Config\Config $config = null,
        \ZfcRbac\Service\AuthorizationService $authService = null
    ) {
        parent::__construct($searchTable, $sessionId, $resultsManager, $config);
        $this->authService = $authService;
    }

    /**
     * Get a list of scheduling options (empty list if scheduling disabled).
     * AK: Also return emtpy list if permission is not granted to show the options.
     *
     * @return array
     */
    public function getScheduleOptions()
    {
        // AK: Check if permission for showing the schedule options is granted
        $hasPermission = $this->authService->isGranted($this->config->Account
            ->scheduled_search_permission);

        // AK: Get schedule options only if permission is granted
        if (!($this->config->Account->schedule_searches ?? false) || !$hasPermission)
        {
            return [];
        }
        return $this->config->Account->scheduled_search_frequencies
            ?? [0 => 'schedule_none', 1 => 'schedule_daily', 7 => 'schedule_weekly'];
    }
}
