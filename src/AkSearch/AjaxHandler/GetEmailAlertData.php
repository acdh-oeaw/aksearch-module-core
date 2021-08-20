<?php
/**
 * AK: "Get email alert popup data" AJAX handler
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
 * @package  AJAX
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ajax_handlers Wiki
 */
namespace AkSearch\AjaxHandler;

use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Session\Settings as SessionSettings;
use VuFind\ILS\Connection;
use VuFind\Auth\ILSAuthenticator;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Config\Config;
use AkSearch\Db\Table\Search as SearchTable;
#use VuFind\Db\Table\Search as SearchTable;

/**
 * AK: "Get email alert popup data" AJAX handler. Get data for "email alert popup".
 * 
 * @category AKsearch
 * @package  AJAX
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ajax_handlers Wiki
 */
class GetEmailAlertData extends \VuFind\AjaxHandler\AbstractIlsAndUserAction
    implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * AK: Solr search results manager
     *
     * @var ResultsManager
     */
    protected $resultsManager;

    /**
     * AK: Config from config.ini
     * 
     * @var Config
     */
    protected $config;

    /**
     * AK: Config from facets.ini
     * 
     * @var Config
     */
    protected $facetsConfig;

    /**
     * AK: Search table
     *
     * @var SearchTable
     */
    protected $searchTable;

    /**
     * Constructor
     *
     * @param SessionSettings  $ss               Session settings
     * @param Connection       $ils              ILS connection
     * @param ILSAuthenticator $ilsAuthenticator ILS authenticator
     * @param User|bool        $user             Logged in user (or false)
     * @param ResultsManager   $rm               Search results manager
     * @param Config           $config           Config from config.ini
     * @param Config           $facetConfig      Config from facets.ini
     * @param SearchTable      $searchTable      Database search table
     */
    public function __construct(SessionSettings $ss, Connection $ils,
        ILSAuthenticator $ilsAuthenticator, $user, ResultsManager $rm,
        Config $config, Config $facetsConfig, SearchTable $searchTable
    ) {
        parent::__construct($ss, $ils, $ilsAuthenticator, $user);
        $this->resultsManager = $rm;
        $this->config = $config;
        $this->facetsConfig = $facetsConfig;
        $this->searchTable = $searchTable;
    }

    /**
     * Handle the AJAX request
     *
     * @param Params $params Parameter from JavaScript AJAX call
     * 
     * @return array A result array with data
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        $parameters = $params->fromQuery() + $params->fromPost();

        // Check which action we should execute
        if ($parameters['action'] == 'checkOpeningPopup') {
            return $this->checkOpeningPopup();
        } else if ($parameters['action'] == 'getData') {
            return $this->getData($params);
        } else {
            // No action
            return false;
        }

    }

    /**
     * Check if the "email alert popup" should be opened or not.
     *
     * @return array An array with an element "data" that is set to either "true" or
     *              "false"
     */
    protected function checkOpeningPopup() {
        // Check if scheduled searches and the popup are activated
        if (!($this->config->Account->schedule_searches ?? false) ||
            !($this->config->Account->scheduled_search_popup ?? false)
        ) {
            return $this->formatResponse(false);
        }

        // Get the logged-in user or false (if not logged in)
        $user = $this->user;

        // If user is not logged in, abort here
        if (!$user) {
            return $this->formatResponse(false);
        }

        // Check if the user wants the see the email alert popup. Default is "false".
        $userWantsPopup = false;
        try {
            $userWantsPopup = $user->show_email_alert_popup;
        } catch (\Exception $ex) {
            // Catch error when we can't get data from the user database table
            $this->logError('Error getting column "show_email_alert_popup" from '
                .'SQL database table "user". Please check if the column exists. '
                .'If you are using MySQL, you can create the table via the '
                .'"Install" page of you VuFind installation. Exception message: '
                .$ex->getMessage());
            return $this->formatResponse('Error when trying to find out if the '
            .'\'email alert popup\' should be presented to the user.', 500);
        }
        // If user don't want to see the email alert popup, abort here
        if (!$userWantsPopup) {
            return $this->formatResponse(false);
        }

        // Get saved scheduled searches in the "search" table for the given user
        $scheduledSearchesForUser = $this->searchTable
            ->getScheduledSearchesForUser($user->id);
        // If user has at least one saved scheduled search, abort here
        if (count($scheduledSearchesForUser) > 0) {
            return $this->formatResponse(false);
        }

        // If we came this far, the popup can be opened
        return $this->formatResponse(true);
    }

    protected function getData(Params $params) {
        // Gather some information, i. e.: facets and schedule options
        $facets = $this->getFacets($params);
        $scheduleOptions = $this->getScheduleOptions();

        // Check if we could gather all necessary information. If not, abort here.
        if (empty($facets) || empty($scheduleOptions)) {
            return $this->formatResponse(false);
        }

        // Everything should be fine. Return the information for opening the popup.
        return $this->formatResponse(compact('facets', 'scheduleOptions'));
    }

    /**
     * Get facets from given params
     *
     * @param Params $params Params for faceting
     * 
     * @return array Array with facets or empty array
     */
    protected function getFacets(Params $params) {
        // Get facet fields from facets.ini
        $facetFields = $this->facetsConfig->EmailAlerts->toArray();

        // Get POST and GET parameters
        $parameters = $params->fromQuery() + $params->fromPost();

        // Get some of the parameters
        $operator = $parameters['facetOperator'] ?? null;
        $backend = $parameters['source'] ?? DEFAULT_SEARCH_BACKEND;

        // Get a query result object
        $results = $this->resultsManager->get($backend);

        // Get the parameters object from the result object
        $paramsObj = $results->getParams();

        foreach ($facetFields as $facetField => $displayText) {
            $paramsObj->addFacet($facetField, $displayText, $operator === 'OR');
        }

        $facets = [];
        try {
            // Get facets
            $facets = $results->getFullFieldFacets(
                array_keys($facetFields), true,-1, null
            );

            // Sort each facet by display text
            foreach ($facets as &$f) {
                $list = &$f['data']['list'];
                $fDisplayText = array_column($list, 'displayText');
                array_multisort (
                    $fDisplayText, SORT_ASC,
                    $list
                );
            }
        } catch (\Exception $ex) {
            // Fail over but log an error
            $this->logError('Faceting request failed. Check for correct configs '
                .'in facets.ini -> [EmailAlerts]. PHP exception message: '
                .$ex->getMessage());
        }
        
        // Get the translated display text
        foreach ($facetFields as $facetField => $displayText) {
            if (isset($facets[$facetField])) {
                $facets[$facetField]['data']['label'] = $displayText;
            }
        }
        
        return $facets;
    }

    /**
     * Get an array of scheduling options (empty array if scheduling disabled).
     *
     * @return array Array of scheduling options or empty array
     */
    public function getScheduleOptions()
    {
        if (!($this->config->Account->schedule_searches ?? false) ||
            !($this->config->Account->scheduled_search_popup ?? false)
        ) {
            return [];
        }

        // Get the schedule options from config.ini
        $scheduleOptions = $this->config->Account->scheduled_search_frequencies;

        // Check if schedule options are set in config.ini. If not, use some default
        // values.
        if (!empty($scheduleOptions)) {
            $scheduleOptions = $scheduleOptions->toArray();
            unset($scheduleOptions['0']);
        } else {
            $scheduleOptions = [1 => 'schedule_daily',
                7 => 'schedule_weekly'];
        }
        
        return $scheduleOptions;
    }
}
