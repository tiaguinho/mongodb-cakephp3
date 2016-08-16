<?php
/**
 * 
 */
namespace Hayko\Mongodb\Database;

use Hayko\Mongodb\Database\Driver\Mongodb as Haykodb;
use Hayko\Mongodb\Database\Schema\MongoSchema;
use Cake\Datasource\ConnectionInterface;
use Cake\Database\Log\LoggedQuery;
use Cake\Database\Log\QueryLogger;


class Connection implements ConnectionInterface {

	/**
	 * Contains the configuration param for this connection
	 * 
	 * @var array
	 */
	 	protected $_config;	

	/**
	 * Database Driver object
	 *
	 * @var resource
	 * @access protected
	 */
		protected $_driver = null;

	 /**
     * Whether to log queries generated during this connection.
     *
     * @var bool
     */
    	protected $_logQueries = false;

    /**
     * Logger object instance.
     *
     * @var \Cake\Database\Log\QueryLogger
     */
    	protected $_logger = null;

	/**
	 * MongoSchema
	 * 
	 * @var MongoSchema
	 * @access protected
	 */
		protected $_schemaCollection;

	/**
	 * creates a new connection with mongodb
	 * 
	 * @param array $config
	 * @access public
	 * @return bool
	 */
		public function __construct($config) {
			$this->_config = $config;
			$this->driver('mongodb', $config);

			if (!empty($config['log'])) {
	            $this->logQueries($config['log']);
	        }
		}

	/**
	 * disconnect existent connection
	 * 
	 * @access public
	 * @return void
	 */
		public function __destruct() {
			if ($this->_driver->connected) {
				$this->_driver->disconnect();
				unset($this->_driver);
			}
		}

	/**
	 * return configuration
	 * 
	 * @return array $_config
	 * @access public
	 */
		public function config() {
			return $this->_config;
		}

	/**
	 * return configuration name
	 * 
	 * @return string
	 * @access public
	 */
		public function configName() {
			return 'mongodb';
		}	

	/**
	 * 
	 */
		public function driver($driver = null, $config = []) {
			if ($driver === null) {
				return $this->_driver;
			}
			$this->_driver = new Haykodb($config);
			return $this->_driver;
		}

	/**
	 * connect to the database
	 * 
	 * @return boolean
	 * @access public
	 */
		public function connect() {
			try {
				$this->_driver->connect();
				return true;
			} catch (Exception $e) {
				throw new MissingConnectionException(['reason' => $e->getMessage()]);
			}
		}

	/**
	 * disconnect from the database
	 * 
	 * @return boolean
	 * @access public
	 */
		public function disconnect() {
			if ($this->_driver->isConnected()) {
				return $this->_driver->disconnect();
			}
			return true;
		}

	/**
	 * database connection status
	 * 
	 * @return booelan
	 * @access public
	 */
		public function isConnected() {
			return $this->_driver->isConnected();
		}

	/**
	 * Gets or sets schema collection for this connection
	 * 
	 * @param $collection
	 * @return \Hayko\Mongodb\Database\Schema\MongoSchema
	 */
		public function schemaCollection($collection = null) {
			return $this->_schemaCollection = new MongoSchema($this->_driver);
		}

	/**
	 * Mongo doesn't support transaction
	 * 
	 * @return false
	 * @access public
	 */
		public function transactional(callable $transaction) {
			return false;
		}

	/**
	 * Mongo doesn't support foreign keys
	 * 
	 * @return false
	 * @access public
	 */
		public function disableConstraints(callable $operation) {
			return false;
		}

	/**
	 * 
	 * @access public
	 * @return 
	 */
		public function logQueries($enable = null) {
			if ($enable === null) {
				return $this->_logQueries;
			}
			$this->_logQueries = $enable;
		}

	/**
	 * 
	 */
		public function logger($instance = null) {
			if ($instance === null) {
	            if ($this->_logger === null) {
	                $this->_logger = new QueryLogger;
	            }
	            return $this->_logger;
	        }
	        $this->_logger = $instance;
		}

	/**
     * Logs a Query string using the configured logger object.
     *
     * @param string $sql string to be logged
     * @return void
     */
	    public function log($sql) {
	        $query = new LoggedQuery;
	        $query->query = $sql;
	        $this->logger()->log($query);
	    }
}