<?php

namespace Hayko\Mongodb\Database;

use Hayko\Mongodb\Database\Driver\Mongodb as Haykodb;
use Hayko\Mongodb\Database\Schema\MongoSchema;

class Connection extends \Cake\Database\Connection
{

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
     * MongoSchema
     *
     * @var MongoSchema
     * @access protected
     */
    protected $_schemaCollection;

    /**
     * disconnect existent connection
     *
     * @access public
     * @return void
     */
    public function __destruct()
    {
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
    public function config()
    {
        return $this->_config;
    }

    /**
     * return configuration name
     *
     * @return string
     * @access public
     */
    public function configName()
    {
        return 'mongodb';
    }

    /**
     * @param null $driver
     * @param array $config
     * @return Haykodb|resource
     */
    public function driver($driver = null, $config = [])
    {
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
    public function connect()
    {
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
    public function disconnect()
    {
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
    public function isConnected()
    {
        return $this->_driver->isConnected();
    }

    /**
     * Gets a Schema\Collection object for this connection.
     *
     * @return MongoSchema
     */
    public function getSchemaCollection()
    {
        if ($this->_schemaCollection !== null) {
            return $this->_schemaCollection;
        }

        return $this->_schemaCollection = new MongoSchema($this->_driver);
    }

    /**
     * Mongo doesn't support transaction
     *
     * @param callable $transaction
     * @return false
     * @access public
     */
    public function transactional(callable $transaction)
    {
        return false;
    }

    /**
     * Mongo doesn't support foreign keys
     *
     * @param callable $operation
     * @return false
     * @access public
     */
    public function disableConstraints(callable $operation)
    {
        return false;
    }

    /**
     * @param null $table
     * @param null $column
     * @return int|string|void
     */
    public function lastInsertId($table = null, $column = null)
    {
        // TODO: Implement lastInsertId() method.
    }
}
