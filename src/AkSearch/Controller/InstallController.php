<?php
/**
 * AK: Extended install controller
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

/**
 * AK: Extending class that controls VuFind auto-configuration.
 *
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class InstallController extends \VuFind\Controller\InstallController
{
    /**
     * AK: Check if the column "show_email_alert_popup" exists in the user table.
     * Displays an appropriate message and button for a fix on the home screen of
     * the install pages. See: https://url.to.vufind/Install/Home
     *
     * @return array
     */
     protected function checkEmailAlertPopup() {
        try {
            $tableAdapter = $this->getTable('user')->getAdapter();
            $tableMetadata = \Laminas\Db\Metadata\Source\Factory::createSourceFromAdapter($tableAdapter);
            $userTableColumns = $tableMetadata->getColumnNames('user');
    
            if (in_array('show_email_alert_popup', $userTableColumns)) {
                // AK: The column exists "show_email_alert_popup". Indicate that
                // everything is OK.
                $returnValue = [
                    'title' => 'E-Mail Alert Popup (OPTIONAL)',
                    'status' => true
                ];
            } else {
                // AK: The column "show_email_alert_popup" does not exist in the user
                // table, so we show an appropriate message.
                $returnValue = [
                    'title' => 'E-Mail Alert Popup (OPTIONAL)',
                    'status' => 'info',
                    'fix' => 'fixemailalertinusertable'
                ];
            }
        } catch (\Exception $ex) {
            // AK: Display an error as something went wrong
            $returnValue = [
                'title' => 'E-Mail Alert Popup (OPTIONAL)',
                'status' => false,
                'fix' => 'fixemailalertinusertable'
            ];
        }
        return $returnValue;
    }

    /**
     * AK: Display instructions for adding the column "show_email_alert_popup" to the
     * user table.
     *
     * @return mixed
     */
    public function fixemailalertinusertableAction() {
        // AK: Create the view model
        $view = $this->createViewModel();

        // AK: Initially set the "showForm" variable
        $view->showForm = false;

        // AK: Check if the overall database setup is correct
        $checkDb = $this->checkDatabase();
        if(!$checkDb['status']) {
            $this->flashMessenger()->addMessage('Database is not set up correctly.
                Before altering tables for the email alerts, the database itself must
                be set up correctly. Please go back to "auto configure" page and fix
                the database setup.', 'error');
            return $view;
        }

        // AK: Check if database setup is for MySQL DB
        $config = $this->getConfig();
        $dbConf = trim($config->Database->database);
        $isMysql = (strtolower(substr($dbConf, 0, 5)) === 'mysql')
            ? true
            : false;

        if (!$isMysql) {
            $this->flashMessenger()->addMessage('Tables can only be altered for a
                MySQL databases. For other DBs (e. g. Postgre SQL), alter the user
                table manually. Table configs can be found at "[VuFind-Base-Dir]/
                module/AkSearch/sql/mysql_email_alerts_popup.sql"', 'error');
            return $view;
        }

        // AK: If we came this far, everything should be OK and the form for adding
        // the show_email_alert_popup column can be shown.
        $view->showForm = true;

        // AK: Get textbox value from the user input field. Defaults to 'root'.
        $view->dbrootuser = $this->params()->fromPost('dbrootuser', 'root');

        if ($this->formWasSubmitted('submit')) {
            try {
                // AK: Get contents of SQL file in AkSearch module
                $sql = file_get_contents(APPLICATION_PATH
                    ."/module/AkSearch/sql/mysql_email_alerts_popup.sql");

                // AK: Create an adapter string from the given adapter string in
                // config.ini at [Database]->database. Override the credentials with
                // the given root user and root password.
                $dbFactory = $this->serviceLocator->get('VuFind\Db\AdapterFactory');
                $db = $dbFactory->getAdapter(
                    $view->dbrootuser,
                    $this->params()->fromPost('dbrootpass')
                );

                // AK: Get all SQL statements from the SQL file and execute them
                $statements = explode(';', $sql);
                foreach ($statements as $current) {
                    // Skip empty sections:
                    if (strlen(trim($current)) == 0) {
                        continue;
                    }
                    $db->query($current, $db::QUERY_MODE_EXECUTE);
                }

                // AK: Display a success message
                $this->flashMessenger()
                    ->addMessage(
                        'Successfully added column "show_email_alert_popup" to the
                        user table. Go back to "auto configure" page to check if
                        everything is OK.', 'success'
                    );
            } catch (\Exception $e) {
                // AK: Show an error message if we catch an exception
                $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            }
        }

        return $view;
    }

    /**
     * AK: Check if loan history table exists. Displays an appropriate message and 
     * button for a fix on the home screen of the install pages.
     * See: https://url.to.vufind/Install/Home
     *
     * @return array
     */
    protected function checkLoanHistoryTable()
    {
        try {
            // AK: Try to read from the loan history table. An exception is thrown if
            // it doesn't exist.
            $loanTable = $this->getTable('loans');
            $loanTable->selectFirst();

            // AK: If no exception is thrown, indicate that everything is OK.
            $returnValue = [
                'title' => 'Loan History (OPTIONAL)',
                'status' => true
            ];
        } catch (\Exception $e) {
            // AK: As an exception is thrown the loan history table seems to not
            // exist. Show an appropriate message.
            $returnValue = [
                'title' => 'Loan History (OPTIONAL)',
                'status' => 'info',
                'fix' => 'fixloanhistorytable'
            ];
        }

        return $returnValue;
    }

    /**
     * AK: Display instructions for creating the loan history table and adding the
     * "save_loans" column to the user table.
     *
     * @return mixed
     */
    public function fixloanhistorytableAction()
    {
        // AK: Create the view model
        $view = $this->createViewModel();

        // AK: Initially set the "showForm" variable
        $view->showForm = false;

        // AK: Check if the overall database setup is correct
        $checkDb = $this->checkDatabase();
        if(!$checkDb['status']) {
            $this->flashMessenger()->addMessage('Database is not set up correctly.
                Before adding and altering tables for the loan history, the 
                database itself must be set up correctly. Please go back to
                "auto configure" page and fix the database setup.', 'error');
            return $view;
        }

        // AK: Check if database setup is for MySQL DB
        $config = $this->getConfig();
        $dbConf = trim($config->Database->database);
        $isMysql = (strtolower(substr($dbConf, 0, 5)) === 'mysql')
            ? true
            : false;

        if (!$isMysql) {
            $this->flashMessenger()->addMessage('Tables can only be created and
                altered for a MySQL databases. For other DBs (e. g. Postgre SQL),
                create the loan history table and alter the user table manually.
                Table configs can be found at
                "[VuFind-Base-Dir]/module/AkSearch/sql/mysql.sql"', 'error');
            return $view;
        }

        // AK: If we came this far, everything should be OK and the form for creating
        // the loan history table and adding the save_loans column can be shown.
        $view->showForm = true;

        // AK: Get textbox value from the user input field. Defaults to 'root'.
        $view->dbrootuser = $this->params()->fromPost('dbrootuser', 'root');

        if ($this->formWasSubmitted('submit')) {
            try {
                // AK: Get contents of SQL file in AkSearch module
                $sql = file_get_contents(
                    APPLICATION_PATH . "/module/AkSearch/sql/mysql.sql"
                );

                // AK: Create an adapter string from the given adapter string in
                // config.ini at [Database]->database. Override the credentials with
                // the given root user and root password.
                $dbFactory = $this->serviceLocator->get('VuFind\Db\AdapterFactory');
                $db = $dbFactory->getAdapter(
                    $view->dbrootuser,
                    $this->params()->fromPost('dbrootpass')
                );

                // AK: Get all SQL statements from the SQL file and execute them
                $statements = explode(';', $sql);
                foreach ($statements as $current) {
                    // Skip empty sections:
                    if (strlen(trim($current)) == 0) {
                        continue;
                    }
                    $db->query($current, $db::QUERY_MODE_EXECUTE);
                }

                // AK: Display a success message
                $this->flashMessenger()
                    ->addMessage(
                        'Successfully created new table "loans" and added new column
                        "save_loans" to the user table. Go back to "auto configure"
                        page to check if everything is OK.', 'success'
                    );
            } catch (\Exception $e) {
                // AK: Show an error message if we catch an exception
                $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            }
        }

        return $view;
    }

}
