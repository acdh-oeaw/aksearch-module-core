<?php

namespace AkSearch\Db\Table;

use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;

class Loans extends \VuFind\Db\Table\Gateway
{
        
    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Zend Framework configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        RowGateway $rowObj = null,
        $table = 'loans'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Get first row of the loans table.
     *
     * @return \Zend\Db\ResultSet\ResultSet Matching row/result set
     */
    public function selectFirst()
    {
        $callback = function ($select) {
            $select->limit(1);
        };
        $this->select($callback);
    }
    
    /**
     * Get loans for a specific user
     *
     * @param string $userId Internal VuFind user ID ("id" field of table "user")
     * @param int    $limit  Number for SQL limit parameter
     * @param int    $offset Number for SQL offset parameter (paging)
     * @param int    $sort   Array for SQL sort parameter. Default is
     *                       ['loan_date' => 'desc']
     * 
     * @return array Top level array with key "count" (the total no. of loans of the
     *               user) and "transactions" (an array of arrays containing the data
     *               of the loans)
     */
    public function selectUserLoans(
        $userId,
        $limit = null,
        $offset = null,
        $sort = ['loan_date' => 'desc']
    ) {
        // Create new SQL "select" for getting the total result count
        $selectCount = $this->getSql()->select();
        $selectCount->columns(
            [
                'id' => new Expression('1'), // Primary key column required by
                                             // TableGateway
                'count' => new Expression('COUNT(id)') // Count the rows
            ]
        );
        // Filter by user ID
        $selectCount->where(['user_id' => $userId]);

        // This would print the whole SQL statement
        // var_dump($this->sql->getSqlStringForSqlObject($selectCount));
        
        // Execute the SQL statement and get the total count
        $resultCount = $this->selectWith($selectCount)->current();
        $totalCount = $resultCount->count;

        // Get the loans for the user using limit, offset and order
        $callback = function ($select) use ($userId, $limit, $offset, $sort) {
            $select->where->equalTo('user_id', $userId) ;
            if ($limit) {
                $select->limit($limit);
            };
            if ($offset) {
                $select->offset($offset);
            }

            // Sort NULL values last if sort direction is "ascending" (asc)
            $sortExpr = null;
            foreach ($sort as $sortField => $sortDir) {
                if (strtolower($sortDir) === 'asc') {
                    $sortArr[] = 'ISNULL(`'.$sortField.'`), `'.$sortField.'` ASC';
                } else {
                    $sortArr[] = '`'.$sortField.'` DESC';
                }
                $sortExpr = implode(',', $sortArr);
            }
            if ($sortExpr) {
                $select->order(new Expression($sortExpr));
            }
        };

        // Execute the SQL "select" and get the results as an array
        $historicLoans = $this->select($callback)->toArray();

        // Return an array with the total count and the SQL results
        return [
            'count' => $totalCount,
            'transactions' => $historicLoans
        ];
    }
    
    /**
     * Get loan by ILS loan ID
     *
     * @param string $loanId The loan ID
     * @return array Loan data as array
     */
    public function getLoanById($loanId)
    {
        $row = $this->select(['ils_loan_id' => $loanId])->current();
        return $row;
    }

    /**
     * Delete all loans for a specific user id
     *
     * @param int  $userId Internal VuFind user id as integer
     * @return int Number of deleted database rows (= loans)
     */
    public function deleteUserLoans($userId) {
        $numbDeleted = $this->delete(['user_id' => $userId]);
        return $numbDeleted;
    }
}
