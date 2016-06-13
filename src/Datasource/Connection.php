<?php
/**
 * 
 */
namespace Mongodb\Datasource;


use Cake\Datasource\ConnectionInterface;


class Connection implements ConnectionInterface {

	private $_config;

	public function __construct($config) {
		pr($config);exit;
	}

	public function configName() {
		return 'mongodb';
	}

	public function config() {
		return $this->_config;
	}
	

	public function transactional(callable $transaction) {}

	public function disableConstraints(callable $operation) {}

	public function logQueries($enable = null) {}

	public function logger($instance = null) {}

}