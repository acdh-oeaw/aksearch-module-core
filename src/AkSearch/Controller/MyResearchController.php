<?php
/**
 * AK: Exteded MyResearch Controller
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
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace AkSearch\Controller;

/**
 * AK: Extending controller for the user account area.
 *
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class MyResearchController extends \VuFind\Controller\MyResearchController
{

    /**
     * Display loan history page for current user
     *
     * @return mixed Returns \Zend\View\Model\ViewModel or Response when exporting
     *               to CSV
     */
    public function historicloansAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Connect to the ILS:
        $catalog = $this->getILS();

        // AK: Initialize varible indicating if loans should be deleted
        $delete = false;

        // AK: Opt-In form was submitted
        if ($this->formWasSubmitted('submitOptIn')) {
            $saveLoanHistory = true;
            $successText = 'setLoanHistoryOptInSuccess';
            $errorTextChkConfirm = 'setLoanHistoryOptInError';
        }

        // AK: Opt-Out form was submitted
        if ($this->formWasSubmitted('submitOptOut')) {
            $saveLoanHistory = false;
            $successText = 'setLoanHistoryOptOutSuccess';
            $errorTextChkConfirm = 'setLoanHistoryOptOutError';
            $delete = true;
        }

        // AK: Opt-in OR Opt-out form was submitted
        if (
            $this->formWasSubmitted('submitOptIn')
            || $this->formWasSubmitted('submitOptOut')
        ) {
            // AK: Check if the confirm-checkbox was checked
            if (!$this->params()->fromPost('chkConfirm', false)) {
                $this->flashMessenger()->addMessage($errorTextChkConfirm, 'error');
            } else {
                try {
                    // AK: Execute this BEFORE getting "$functionConfig" below so
                    //     that $functionConfig contains the possibly changed value.
                    $catalog->saveMyTransactionHistory(
                        $patron,
                        $saveLoanHistory
                    );

                    if ($delete) {
                        // AK: If we get this far the loans can be deleted
                        $catalog->deleteMyTransactionHistory($patron);
                    }
                    $this->flashMessenger()->addMessage($successText, 'success');
                } catch (\VuFind\Exception\ILS $e) {
                    $this->flashMessenger()->addErrorMessage($e->getMessage());
                    return $this->createViewModel();
                } catch (\Exception $e) {
                    $this->flashMessenger()->addErrorMessage(
                        'ils_action_unavailable'
                    );
                    return $this->createViewModel();
                }
            }
        }

        // Check function config (AK: Gets the configs from getConfig in ILS driver)
        $functionConfig = $catalog->checkFunction(
            'getMyTransactionHistory', $patron
        );

        // AK: Check if the driver is able to save the users decision about opting
        //     in or out to saving the loan history.
        $canSetSaveHistory = $catalog->checkCapability(
            'saveMyTransactionHistory'
        );

        // AK: Show a message if loan history can't be used.
        if (false === $functionConfig || false === $canSetSaveHistory) {
            $this->flashMessenger()->addErrorMessage('ils_action_unavailable');
            return $this->createViewModel();
        }

        // AK: Export loan history
        if ($this->formWasSubmitted('submitExportLoanHistory')) {
            
            // AK: Create the downloadable CSV file response
            $catalog->exportMyTransactionHistory($patron);

            // AK: Return response - disables rendering of view and layout.
            //     Otherwise weird stuff gets appended to the CSV output file.
            return $this->response;
        }

        // Get paging setup:
        $config = $this->getConfig();
        $pageOptions = $this->getPaginationHelper()->getOptions(
            (int)$this->params()->fromQuery('page', 1),
            $this->params()->fromQuery('sort'),
            $config->Catalog->historic_loan_page_size ?? 50,
            $functionConfig
        );

        // Get checked out item details:
        $result
            = $catalog->getMyTransactionHistory($patron, $pageOptions['ilsParams']);

        if (isset($result['success']) && !$result['success']) {
            $this->flashMessenger()->addErrorMessage($result['status']);
            return $this->createViewModel();
        }

        $paginator = $this->getPaginationHelper()->getPaginator(
            $pageOptions, $result['count'], $result['transactions']
        );
        if ($paginator) {
            $pageStart = $paginator->getAbsoluteItemNumber(1) - 1;
            $pageEnd = $paginator->getAbsoluteItemNumber($pageOptions['limit']) - 1;
        } else {
            $pageStart = 0;
            $pageEnd = $result['count'];
        }

        $transactions = $hiddenTransactions = [];
        foreach ($result['transactions'] as $i => $current) {
            // Build record driver (only for the current visible page):
            if ($pageOptions['ilsPaging'] || ($i >= $pageStart && $i <= $pageEnd)) {
                $transactions[] = $this->getDriverForILSRecord($current);
            } else {
                $hiddenTransactions[] = $current;
            }
        }

        $sortList = $pageOptions['sortList'];
        $params = $pageOptions['ilsParams'];

        // AK: Create the view model
        $view = $this->createViewModel(
            compact(
                'transactions', 'paginator', 'params',
                'hiddenTransactions', 'sortList', 'functionConfig'
            )
        );

        // AK: Return the view model
        return $view;
    }


}
