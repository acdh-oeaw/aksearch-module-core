<?php

namespace AkSearch\Db\Row;

use VuFind\Db\Row\RowGateway;

class Loans extends RowGateway {
		
	/**
	 * Constructor
	 *
	 * @param \Zend\Db\Adapter\Adapter $adapter Database adapter
	 */
	public function __construct($adapter) {
		parent::__construct('id', 'loans', $adapter);
	}
}
?>