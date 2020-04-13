<?php
/**
 * AK: Extended Alma controller
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
 * AK: Extending Alma controller, mainly for webhooks.
 *
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class AlmaController extends \VuFind\Controller\AlmaController
{
    /**
     * Action that is executed when the webhook page is called.
     *
     * @return \Zend\Http\Response|NULL
     */
    public function webhookAction()
    {
        // Request from external
        $request = $this->getRequest();

        // Get request method (GET, POST, ...)
        $requestMethod = $request->getMethod();

        // Get request body if method is POST and is not empty
        $requestBodyJson = null;
        if ($request->getContent() != null
            && !empty($request->getContent())
            && $requestMethod == 'POST'
        ) {
            try {
                $this->checkMessageSignature($request);
            } catch (\VuFind\Exception\Forbidden $ex) {
                return $this->createJsonResponse(
                    'Access to Alma Webhook is forbidden. ' .
                    'The message signature is not correct.', 403
                );
            }
            $requestBodyJson = json_decode($request->getContent());
        }

        // Get webhook action
        $webhookAction = $requestBodyJson->action ?? null;

        // Perform webhook action
        switch ($webhookAction) {

        case 'USER':
            $accessPermission = 'access.alma.webhook.user';
            try {
                $this->checkPermission($accessPermission);
            } catch (\VuFind\Exception\Forbidden $ex) {
                return $this->createJsonResponse(
                    'Access to Alma Webhook \'' . $webhookAction . '\' forbidden. ' .
                    'Set permission \'' . $accessPermission .
                    '\' in \'permissions.ini\'.', 403
                );
            }

            return $this->webhookUser($requestBodyJson);
            break;
        case 'LOAN':
            $accessPermission = 'access.alma.webhook.loan';
            try {
                $this->checkPermission($accessPermission);
            } catch (\VuFind\Exception\Forbidden $ex) {
                return $this->createJsonResponse(
                    'Access to Alma Webhook \'' . $webhookAction . '\' forbidden. ' .
                    'Set permission \'' . $accessPermission .
                    '\' in \'permissions.ini\'.', 403
                );
            }

            return $this->webhookLoan($requestBodyJson);
            break;
        case 'JOB_END':
        case 'NOTIFICATION':
        case 'REQUEST':
        case 'BIB':
        case 'ITEM':
            return $this->webhookNotImplemented($webhookAction);
                break;
        default:
            $accessPermission = 'access.alma.webhook.challenge';
            try {
                $this->checkPermission($accessPermission);
            } catch (\VuFind\Exception\Forbidden $ex) {
                return $this->createJsonResponse(
                    'Access to Alma Webhook challenge forbidden. Set permission \'' .
                    $accessPermission . '\' in \'permissions.ini\'.', 403
                );
            }
            return $this->webhookChallenge();
                break;
        }
    }

    public function webhookLoan($requestBodyJson)
    {
        // Get event from webhook
        $event = $requestBodyJson->event->value ?? null;

        $ilsUserId = $requestBodyJson->item_loan->user_id;

        // Get the MySQL user table
        $userTable = $this->getTable('user');

        // Get user data from VuFind table
        $user = $userTable->getByCatalogId($ilsUserId);

        try {
            // Check if the user wants to save his loans
            $saveLoans = filter_var($user->save_loans, FILTER_VALIDATE_BOOLEAN);

            // If the user doesn't want to save his loans, return an appropriate
            // message and stop further code executing.
            if (!$saveLoans) {
                return $this->createJsonResponse('User with ID ' . $ilsUserId .
                    ' doesn\'t want to save his loan history', 200);
            }

            // Get the internal VuFind user ID for the currently logged in user
            $vufindUserId = $user->id;
        } catch (\Exception $e) {
            $errorMsg = 'Could not find user with Alma user ID ' . $ilsUserId .
                ' in VuFind database';
            return $this->createErrorResponse($errorMsg, 404, $e);
        }

        // Get MySQL loans table
        $loansTable = $this->getTable('loans');

        // Get ILS loan ID
        $ilsLoanId = $requestBodyJson->item_loan->loan_id;

        if ($event == 'LOAN_CREATED') {
            try {
                // Create array with loan details from webhook message
                $loan['ils_loan_id'] = $ilsLoanId;
                $loan['user_id'] = $vufindUserId;
                $loan['ils_user_id'] = $ilsUserId;
                $loan['bib_id'] = $requestBodyJson->item_loan->mms_id;
                $loan['title'] = $requestBodyJson->item_loan->title;
                $loan['author'] = $requestBodyJson->item_loan->author;
                $loan['publication_year'] =
                    $requestBodyJson->item_loan->publication_year;
                $loan['description'] = $requestBodyJson->item_loan->description;
                $loan['loan_date'] =
                    $this->getSqlDate($requestBodyJson->item_loan->loan_date);
                $loan['due_date'] = 
                    $this->getSqlDate($requestBodyJson->item_loan->due_date);
                $loan['return_date'] = null;
                $loan['library_code'] = $requestBodyJson->item_loan->library->value;
                $loan['location_code'] =
                    $requestBodyJson->item_loan->location_code->value;
                $loan['borrowing_location_code'] =
                    $requestBodyJson->item_loan->circ_desk->value;
                $loan['call_no'] = $requestBodyJson->item_loan->call_number;
                $loan['barcode'] = $requestBodyJson->item_loan->item_barcode;

                // Insert loan details into loans table
                $loansTable->insert($loan);

                return $this->createJsonResponse(
                    'Loan sucessfully created from Alma webhook in VuFind loans ' .
                    'table. ILS loan ID: ' . $ilsLoanId, 200
                );
            } catch (\Exception $e) {
                $errorMsg = 'Could not add information from Alma loan webhook to ' .
                    'the VuFind loans table.';
                return $this->createErrorResponse($errorMsg, 400, $e);
            }
        }

        // Update loan due date in loans table for existing loan
        if ($event == 'LOAN_DUE_DATE' || $event == 'LOAN_RENEWED') {
            try {
                // Get new due date from Alma webhook message
                $newDueDate = $this->getSqlDate(
                    $requestBodyJson->item_loan->due_date
                );

                // Get loan by loan ID
                $loan = $loansTable->getLoanById($ilsLoanId);

                // Set new due date
                $loan->due_date = $newDueDate;
                $loan->save();

                // Return message
                return $this->createJsonResponse('Successfully updated due date ' .
                    'from Alma loan webhook in VuFind loans table for loan id ' .
                    $ilsLoanId, 200);
            } catch (\Exception $e) {
                $errorMsg = 'Could not update due date from Alma loan webhook in ' .
                    'the VuFind loans table for loan id ' . $ilsLoanId;
                return $this->createErrorResponse($errorMsg, 400, $e);
            }
        }

        // Insert loan return date into loans table
        if (
                $event == 'LOAN_RETURNED' ||
                $event == 'LOAN_LOST' ||
                $event == 'LOAN_CLAIMED_RETURNED'
            ) {
            try {
                // Get return date from Alma webhook message
                $returnDate = $this->getSqlDate($requestBodyJson->time);

                // Get loan by loan ID
                $loan = $loansTable->getLoanById($ilsLoanId);

                // Set return date
                $loan->return_date = $returnDate;
                $loan->save();

                // Return message
                return $this->createJsonResponse('Successfully added return date ' .
                    'from Alma loan webhook to VuFind loans table for loan id ' .
                    $ilsLoanId, 200);
            } catch (\Exception $e) {
                $errorMsg = 'Could not add return date from Alma loan webhook to ' .
                    'the VuFind loans table for loan id ' . $ilsLoanId;
                return $this->createErrorResponse($errorMsg, 400, $e);
            }
        }
    }


    /**
     * Send the "set password email" to a new user that was created in Alma and sent
     * to VuFind via webhook.
     * 
     * AK: Send HTML (mime) e-mail instead of "just text" e-mail
     *
     * @param \VuFind\Db\Row\User $user   A user row object from the VuFind
     *                                    user table.
     * @param \Zend\Config\Config $config A config object of config.ini
     *
     * @return void
     */
    protected function sendSetPasswordEmail($user, $config)
    {
        // If we can't find a user
        if (null == $user) {
            error_log(
                'Could not send the email to new user for setting the ' .
                'password because the user object was not found.'
            );
        } else {
            // Attempt to send the email
            try {
                // Create a fresh hash
                $user->updateHash();
                $config = $this->getConfig();
                $renderer = $this->getViewRenderer();
                $method = $this->getAuthManager()->getAuthMethod();

                // AK: Get template for welcome e-mail when user is created via
                //     Alma webhook.
                $message = $renderer->render(
                    'Email/new-user-welcome-almawebhook.phtml', [
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'username' => $user->username,
                    'url' => $this->getServerUrl('myresearch-verify') . '?hash=' .
                        $user->verify_hash . '&auth_method=' . $method
                    ]
                );
                
                // AK: Get configs from Alma.ini
                $almaConfig = $this->getConfig('Alma');
                $bcc = $almaConfig->Webhook->new_user_welcome_email_bcc;
                $from = $almaConfig->Webhook->new_user_welcome_email_from
                        ?? $config->Site->email ?: $config->Site->email;
                $replyTo = $almaConfig->Webhook->new_user_welcome_email_replyto;
                
                // AK: Send the mime e-mail
                $this->serviceLocator->get(\AkSearch\Mailer\Mailer::class)
                    ->sendMimeMail($user->email, $from,
                    $this->translate('new_user_welcome_almawebhook_subject'),
                    $message, $replyTo, null, $bcc, null);
            } catch (\VuFind\Exception\Mail $e) {
                error_log(
                    'Could not send the \'set-password-email\' to user with ' .
                    'primary ID \'' . $user->cat_id . '\' | username \'' .
                    $user->username . '\': ' . $e->getMessage()
                );
            }
        }
    }

    /**
     * Convenience method for creating an error response
     *
     * @param string $msg Custom error message
     * @param int $httpStatusCode HTTP error code
     * @param \Exception $exception The whole PHP "Exception" object
     * @return void
     */
    public function createErrorResponse($msg, $httpStatusCode, $exception = null)
    {
        $errMsg = $msg . (($exception) ? ' | ' . $exception->getMessage() : '');
        error_log($errMsg);
        return $this->createJsonResponse($errMsg, $httpStatusCode);
    }

    /**
     * Get a date/time format suitable for an SQL datetime column from a webhook
     * date/time string.
     *
     * @param string  $date The date/time string from the webhook. Format must be
     *                      'Y-m-d\TH:i:s.u\Z' or 'Y-m-d\TH:i:s\Z'
     * @return string Date/Time formatted to 'Y-m-d H:i:s'
     */
    protected function getSqlDate($date)
    {
        $dateConverter = new \VuFind\Date\Converter($this->config->toArray());
        try {
            $dateSql = $dateConverter->convert(
                'Y-m-d\TH:i:s.u\Z',
                'Y-m-d H:i:s',
                $date
            );
        } catch (\Exception $e) {
            try {
                $dateSql = $dateConverter->convert(
                    'Y-m-d\TH:i:s\Z',
                    'Y-m-d H:i:s', 
                    $date
                );
            } catch (\Exception $e) {
                return $this->createErrorResponse(
                    'Error when converting date/time ' . $date . ' from Alma ' .
                    'webhook to SQL date/time in format Y-m-d H:i:s', 400, $e);
            }
        }
        return $dateSql;
    }
}
