<?php
/**
 * AK: Extended MyResearch Controller
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

use VuFind\Exception\Auth as AuthException;

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
     * "Create account" action
     *
     * @return mixed
     */
    public function accountAction()
    {
        // If the user is already logged in, don't let them create an account:
        if ($this->getAuthManager()->isLoggedIn()) {
            return $this->redirect()->toRoute('myresearch-home');
        }
        // If authentication mechanism does not support account creation, send
        // the user away!
        $method = trim($this->params()->fromQuery('auth_method'));
        if (!$this->getAuthManager()->supportsCreation($method)) {
            return $this->forwardTo('MyResearch', 'Home');
        }

        // If there's already a followup url, keep it; otherwise set one.
        if (!$this->getFollowupUrl()) {
            $this->setFollowupUrlToReferer();
        }

        // Make view
        $view = $this->createViewModel();

        // Password policy
        $view->passwordPolicy = $this->getAuthManager()
            ->getPasswordPolicy($method);

        // Set up reCaptcha
        $view->useRecaptcha = $this->recaptcha()->active('newAccount');

        // Pass request to view so we can repopulate user parameters in form:
        $view->request = $this->getRequest()->getPost();

        // Process request, if necessary:
        if ($this->formWasSubmitted('submit', $view->useRecaptcha)) {
            try {
                // AK: Get the request object
                $request = $this->getRequest();

                // AK: Create the user for the particular auth method
                $user = $this->getAuthManager()->create($request);

                // AK: Check if a welcome e-mail should be sent to the user
                if ($this->getAuthManager()->supportsWelcomeEmail()) {
                    $this->sendWelcomeEmail($request, $user);
                }

                // AK: Check if an information e-mail should be sent to the library
                if ($this->getAuthManager()->supportsLibraryEmail()) {
                    $this->sendEmailToLibrary($request, $user);
                }

                // TODO: Forward to SUCCESS site!
                return $this->forwardTo('MyResearch', 'Home');
            } catch (AuthException $e) {
                $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            }
        } else {
            // If we are not processing a submission, we need to simply display
            // an empty form. In case ChoiceAuth is being used, we may need to
            // override the active authentication method based on request
            // parameters to ensure display of the appropriate template.
            $this->setUpAuthenticationFromRequest();
        }
        return $view;
    }

    /**
     * AK: Send e-mail to library if account was created successfully for a patron.
     * 
     * @param \Zend\Http\Request  $request Request object from the form
     * @param \VuFind\Db\Row\User $user    User row object from the database
     * 
     * @throws \VuFind\Exception\Mail
     * 
     * @return void
     */
    protected function sendEmailToLibrary($request, $user) {
        // Check attachments (errors, mime type and size)
        $atts = $this->checkAttachments($request->getFiles());

        // Get view renderer
        $renderer = $this->getViewRenderer();

        // Get variables for e-mail and subject
        $vars = $this->getAuthManager()->getLibraryEmailVars($request, $user);

        // Render template for e-mail with variables defined by the auth method
        $body = $renderer->render('Email/new-user-library.phtml', $vars['text']);

        // Translate subject for e-mail
        $subject = $this->translate(
            'new_user_library_email_subject',
            $vars['subject']
        );

        // Get config.ini
        $config = $this->getConfig();

        // Send mime e-mail
        $this->serviceLocator->get('AkSearch\Mailer\Mailer')->sendMimeMail(
            $config->Authentication->library_email,
            $config->Authentication->welcome_email_from
                ?? $config->Site->email
                ?? null,
            $subject, $body, null, null, null, $atts
        );
    }

    /**
     * AK: Send e-mail to user if account was created successfully.
     * 
     * @param \Zend\Http\Request  $request Request object from the form
     * @param \VuFind\Db\Row\User $user    User row object from the database
     * 
     * @throws \VuFind\Exception\Mail
     * 
     * @return void
     */
    protected function sendWelcomeEmail($request, $user) {
        // Get view renderer
        $renderer = $this->getViewRenderer();

        // Get variables for e-mail and subject
        $vars = $this->getAuthManager()->getWelcomeEmailVars($request, $user);

        // Render template for e-mail with variables defined by the auth method
        $body = $renderer->render('Email/new-user-welcome.phtml', $vars['text']);

        // Translate subject for e-mail
        $subject = $this->translate('new_user_welcome_email_subject');

        // Get config.ini
        $config = $this->getConfig();

        // Get e-mail address from [Site] config
        $siteMail = $config->Site->email;

        // Send mime e-mail
        $this->serviceLocator->get('AkSearch\Mailer\Mailer')->sendMimeMail(
            $user->email,
            $config->Authentication->welcome_email_from
                ?? $siteMail
                ?? null,
            $subject, $body,
            $config->Authentication->welcome_email_replyto
                ?? $siteMail
                ?? null,
            null,
            $config->Authentication->welcome_email_bcc
                ?? $siteMail
                ?? null
        );
    }

    /**
     * AK: Removes attachments with errors. For the remaining attachments: Checks if
     *     they have an accepted mime type and if they are of an accepted file size.
     *
     * @param \Zend\Stdlib\Parameters $attachments The file object from the request
     * 
     * @throws AuthException
     * 
     * @return \Zend\Stdlib\Parameters Valid attachments
     */
    protected function checkAttachments($attachments) {
        // Create new parameters object for attachements without errors. Erroneous
        // attachments are mainly from empty file pickers in the form where no file
        // was chosen.
        $atts = new \Zend\Stdlib\Parameters();
        $uploadFileValidator = new \Zend\Validator\File\UploadFile();
        foreach ($attachments as $key => $attachment) {
            $isValid = $uploadFileValidator->isValid($attachment);
            if ($isValid) {
                $atts->append($attachment);
            }
        }
        
        // Get allowed mime types from config
        $allowedMimeTypesConf =
            $this->almaConfig['NewUser']['fileAttachmentMimeTypes'] ?? '';
        $allowedMimeTypes = preg_split('/\s*,\s*/', $allowedMimeTypesConf, null,
            PREG_SPLIT_NO_EMPTY
        );

        // Get allowed file size. Default is 10MB if not set.
        $allowedSize = $this->almaConfig['NewUser']['fileAttachmentSize'] ?? '10MB';

        foreach ($atts as $att) {
            // Validate mime type
            if ($allowedMimeTypes) {
                $mimeTypeValidator = new \Zend\Validator\File\MimeType(
                    $allowedMimeTypes
                );
                if (!$mimeTypeValidator->isValid($att)) {
                    throw new AuthException('mimeTypeError');
                }
            }

            // Validate size
            $sizeValidator = new \Zend\Validator\File\Size(['max' => $allowedSize]);
            if (!$sizeValidator->isValid($att)) {
                throw new AuthException('sizeError');
            }
        }

        return $atts;
    }

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


    /**
     * Execute the request
     * AK: Also get technical failure message on whoops error pages
     *
     * @param \Zend\Mvc\MvcEvent $event Event
     *
     * @return mixed
     * @throws Exception\DomainException
     */
    public function onDispatch(\Zend\Mvc\MvcEvent $event)
    {
        // Catch any ILSExceptions thrown during processing and display a generic
        // failure message to the user (instead of going to the fatal exception
        // screen). This offers a slightly more forgiving experience when there is
        // an unexpected ILS issue. Note that most ILS exceptions are handled at a
        // lower level in the code (see \VuFind\ILS\Connection and the config.ini
        // loadNoILSOnFailure setting), but there are some rare edge cases (for
        // example, when the MultiBackend driver fails over to NoILS while used in
        // combination with MultiILS authentication) that could lead here.

        try {
            // AK: Get parents parent because we need to catch their exeptions
            $grandParentClass = get_parent_class(parent::class);
            return $grandParentClass::onDispatch($event);
        } catch (ILSException $exception) {
            // Always display generic message:
            $this->flashMessenger()->addErrorMessage('ils_connection_failed');
            // In development mode, also show technical failure message:
            if ('development' == APPLICATION_ENV) {
                $this->flashMessenger()->addErrorMessage($exception->getMessage());
                // AK: Throw exception with correct error message for whoops
                throw new \ILSException($exception->getMessage());
            }
            return $this->createViewModel();
        }
    }


}
