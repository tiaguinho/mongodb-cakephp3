<?php

namespace Hayko\Mongodb\Database\Schema;

use Hayko\Mongodb\Database\Driver\Mongodb;
use Cake\Database\Schema\TableSchema;

class MongoSchema
{

    /**
     * Database Connection
     *
     * @var resource
     * @access protected
     */
    protected $_connection = null;

    /**
     * Constructor
     *
     * @param Mongodb $conn
     * @access public
     */
    public function __construct(Mongodb $conn)
    {
        $this->_connection = $conn;
    }

    /**
     * Describe
     *
     * @access public
     * @param $name
     * @return TableSchema
     */
    public function describe($name)
    {
        if (strpos($name, '.')) {
            list(, $name) = explode('.', $name);
        }

        $table = new TableSchema($name);

        if (empty($table->primaryKey())) {
            $table->addColumn('_id', ['type' => 'string', 'default' => new \MongoDB\BSON\ObjectId(), 'null' => false]);
            $table->addConstraint('_id', ['type' => 'primary', 'columns' => ['_id']]);
        }

        return $table;
    }
}
