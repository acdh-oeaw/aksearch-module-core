<?php
/**
 * AK: Controller for the email alert popup
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
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace AkSearch\Controller;

use Laminas\Session\SessionManager;

/**
 * AK: Controller class for the email alert popup
 * 
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class EmailAlertController extends \VuFind\Controller\AbstractBase
    implements \Laminas\Log\LoggerAwareInterface
{

    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Process requests for email alert popup.
     *
     * @return mixed
     */
    public function processorAction()
    {
        return $this->forwardTo('EmailAlert', $this->getActionFromRequest());
    }

    /**
     * The action to display the popup form with the data that are needed to create
     * an email alert.
     *
     * @return mixed
     */
    public function popupAction()
    {
        // Force user to login
        if (!$this->getUser()) {
            return $this->forceLogin('email_alert_login_warning');
        }
        $view = $this->createViewModel();
        $view->userIsLoggedIn = true;
        return $view;
    }

    /**
     * The action to display when the popup was canceled.
     *
     * @return mixed
     */
    public function cancelpopupAction()
    {
        // Send the user away if not logged in
        if (!$this->getUser()) {
            return $this->forwardTo('Search', 'Home');
        }

        $view = $this->createViewModel();

        // Set success variable to view. Default is "false".
        $view->success = false;

        // Check if a user is given
        if ($user = $this->getUser()) {
            try {
                // Set "show_email_alert_popup" to 0 (= false)
                $user->show_email_alert_popup = 0;
                $user->save();
                $view->success = true;
            } catch (\Exception $ex) {
                // Log error and fail over with "$view->success = false"
                $this->logError('Failed to cancel email alert popup (see '
                    .'EmailAlertController->cancelpopupAction()). Exception '
                    .'message: '.$ex->getMessage());
            }
        } else {
            // Log error and fail over with "$view->success = false"
            $this->logError('No user given when trying to cancel email alert popup '
            .'in EmailAlertController->subscribeAction()');
        }
        
        return $view;
    }

    /**
     * The action to display when the user subscribes to an email alert.
     *
     * @return mixed
     */
    public function subscribeAction()
    {
        // Send the user away if not logged in
        if (!$this->getUser()) {
            return $this->forwardTo('Search', 'Home');
        }

        // Create a view model
        $view = $this->createViewModel();

        // Set success variable to view. Default is "false".
        $view->success = false;

        // Check if a user is given
        if ($user = $this->getUser()) {
            // Get base URL
            $baseUrl = rtrim($this->getServerUrl('home'), '/');

            // Get session ID
            $sessId = $this->serviceLocator->get(SessionManager::class)->getId();

            // Get request data - allow both GET and POST variables:
            $parameters = $this->params()->fromQuery() + $this->params()->fromPost();

            // Get a search runner for executing a search
            $runner = $this->serviceLocator->get(\VuFind\Search\SearchRunner::class);
    
            // Create request parameters for our use case: get filter from user
            // input and set "AllFields" with an empty "lookfor" string for emulating
            // an "empty search".
            $request = [
                'filter' => $parameters['filter'],
                'type' => 'AllFields',
                'lookfor' => ''
            ];
    
            try {
                // Run a search with the given parameters and get the results
                $results = $runner->run($request);

                // Get a results manager
                $resultsManager = $this->serviceLocator
                    ->get(\VuFind\Search\Results\PluginManager::class);
    
                // Set up URL query helper
                $results->getUrlQuery()->getParams(false);
    
                // Save the search to the "Search" table
                $searchTable = $this->getTable('Search');
                $searchRow = $searchTable->saveSearch($resultsManager, $results,
                    $sessId, $user->id ?? null);
                
                // Set some additinal information to the saved search
                $searchRow->saved = 1;
                $searchRow->notification_frequency =
                    $parameters['scheduleOptions'] ?? '7';
                $searchRow->user_id = $user->id;
                $searchRow->notification_base_url = $baseUrl;
                $searchRow->save();

                // Set the success variable for the view to "true"
                $view->success = true;
            } catch (\Exception $ex) {
                // Log error and fail over with "$view->success = false"
                $this->logError('Failed to save a new search from the email alert '
                    .'popup (see EmailAlertController->subscribeAction()). '
                    .'Exception message: '.$ex->getMessage());
            }
        } else {
            // Log error and fail over with "$view->success = false"
            $this->logError('No user given when trying to save new search from the '
                .'email alert popup in EmailAlertController->subscribeAction()');
        }
        
        // Return the view model
        return $view;
    }

    /**
     * Get the action from the request.
     *
     * @return string
     */
    protected function getActionFromRequest()
    {
        $returnValue = 'Popup';
        if ($this->getUser()) {
            if (strlen($this->params()->fromPost('cancelpopup', '')) > 0) {
                $returnValue = 'CancelPopup';
            } elseif (strlen($this->params()->fromPost('subscribe', '')) > 0) {
                $returnValue = 'Subscribe';
            }
        }
        return $returnValue;
    }

}